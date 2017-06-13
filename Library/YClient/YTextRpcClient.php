<?php
namespace YClient;

use \Exception;
/**
 * 版本 1.2.0 
 * 发布时间 2016-04-12
 * 2016-04-12 增加异步支持
 * 
 * 新 RPC 文本协议客户端实现
 *
 * @usage:
 *  1, 复制或软链接 YTextRpcClient.php 到具体的项目目录中
 *  2, 添加 RpcServer 相关配置, 参考: examples/config/debug.php
 *  3, 在 Controller 中添加 RPC 使用代码, 参考下面的例子
 *
 * @example
 *
 *      $userInfo = RpcClient_User_Info::instance();
 *
 *      # case 1
 *      $result = $userInfo->getInfoByUid(100);
 *      if (!YTextRpcClient::hasErrors($result)) {
 *          ...
 *      }
 *
 *      # case 2
 *      $userInfo->getInfoByUid(100, function ($result, $errors) {
 *          if (!$errors) {
 *              ...
 *          }
 *      });
 *
 *      # 其中 RpcClient_ 是接口调用约定
 *      # RpcClient_User_Info::getInfoByUid 映射到
 *      # WebService 中的 \User\Service\Info 类和 getInfoByUid 方法
 *
 * 用户认证算法
 *
 *      # 客户端
 *      $packet = array(
 *          'data' => json_encode(
 *              array(
 *                  'version' => '2.0',
 *                  'user' => $this->rpcUser,
 *                  'password' => md5($this->rpcUser . ':' . $this->rpcSecret),
 *                  'timestamp' => microtime(true)); # 时间戳用于生成不同的签名, 以区分每一个独立请求
 *                  'class' => $this->rpcClass,
 *                  'method' => $method,
 *                  'params' => $arguments,
 *              )
 *          ),
 *      );
 *      $packet['signature'] = $this->encrypt($packet['data'], $secret);
 *
 *      # 服务器端
 *      # $this->encrypt($rawJsonData, $secret) === $packet['signature']
 *
 *
 */


/**
 * 客户端协议实现.
 */
class YTextRpcClient
{
    /**
     * 异步调用发送数据前缀
     * @var string
     */
    const ASYNC_SEND_PREFIX = 'asend_';

    /**
     * 异步调用接收数据前缀
     * @var string
     */
    const ASYNC_RECV_PREFIX = 'arecv_';

    public $timeout = 18;
    protected $connection;
    protected $rpcClass;
    protected $rpcUri;
    protected $rpcUser;
    protected $rpcSecret;
    protected $executionTimeStart;
    
    private static $allowReturnObject = false;

    protected $asyncInstances = array();

    /**
     * 设置或读取配置信息.
     *
     * @param array $config 配置信息.
     *
     * @return array|void
     */
    public static function config(array $config = array())
    {
        static $_config = array();
        if (empty($config)) {
            return $_config;
        }
        if (isset($config['allow_return_object']) && $config['allow_return_object']) {
            self::$allowReturnObject = true;
        }
        $_config = $config;
    }

    /**
     * 获取RPC对象实例.
     *
     * @param array $config 配置信息, 或配置节点.
     *
     * @return YTextRpcClient
     */
    public static function instance($config = array())
    {
        $className = get_called_class();
        $instances = new $className($config, $className);
        return $instances;
    }

    /**
     * 检查返回结果是否包含错误信息.
     *
     * @param mixed $ctx 调用RPC接口时返回的数据.
     *
     * @return boolean
     */
    public static function hasErrors(&$ctx)
    {
        if (is_array($ctx)) {
            if (isset($ctx['error']['message'])) {
                $ctx = $ctx['error'];
                return true;
            }
            if (isset($ctx['errors']['message'])) {
                $ctx = $ctx['errors'];
                return true;
            }
        } elseif(is_object($ctx)) {
            if (isset($ctx->error->message)) {
                $ctx = $ctx->error;
                return true;
            }
            if (isset($ctx->errors->message)) {
                $ctx = $ctx->errors;
                return true;
            }
        }

        return false;
    }

    /**
     * 构造函数.
     *
     * @param array $config 配置信息, 或配置节点.
     *
     * @throws Exception 抛出开发错误信息.
     */
    private function __construct(array $config = array(), $className = '')
    {
        if (empty($config)) {
            $config = self::config();
        } else {
            self::config($config);
        }

        $config = self::config();
        if(empty($config) && class_exists('\Config\YClient'))
        {
            $config = (array) new \Config\YClient;
            self::config($config);
        }

        if (empty($config)) {
            throw new Exception('YTextRpcClient: Missing configurations');
        }

        $className = $className ? $className : get_called_class();
        $this->rpcClass = $className;
        if (preg_match('/^[A-Za-z0-9]+_([A-Za-z0-9]+)/', $className, $matches)) {
            $module = $matches[1];
            if (empty($config[$module])) {
                throw new Exception(sprintf('YTextRpcClient: Missing configuration for `%s`', $module));
            } else {
                $this->init($config[$module]);
            }
        } else {
            throw new Exception(sprintf('YTextRpcClient: Invalid class name `%s`', $className));
        }
    }

    /**
     * 读取初始化配置信息.
     *
     * @param array $config 配置.
     *
     * @return void
     */
    public function init(array $config)
    {
        $this->rpcUri = $config['uri'];
        $this->rpcUser = $config['user'];
        $this->rpcSecret = $config['secret'];
        $this->rpcCompressor = isset($config['compressor']) ? strtoupper($config['compressor']) : null;
    }

    /**
     * 创建网络链接.
     *
     * @throws Exception 抛出链接错误信息.
     *
     * @return void
     */
    private function openConnection()
    {
        $this->connection = @stream_socket_client($this->rpcUri, $errno, $errstr, $this->timeout);
        if (!$this->connection) {
            throw new Exception(sprintf('YTextRpcClient connect %s, %s, (%.3fs)', $this->rpcUri, $errstr, $this->executionTime()));
        }
    }

    /**
     * 关闭网络链接.
     *
     * @return void
     */
    private function closeConnection()
    {
        @fclose($this->connection);
    }

    /**
     * 请求数据签名.
     *
     * @param string $data   待签名的数据.
     * @param string $secret 私钥.
     *
     * @return string
     */
    private function encrypt($data, $secret)
    {
        return md5($data . '&' . $secret);
    }

    /**
     * 调用
     * @param string $method
     * @param array $arguments
     * @throws Exception
     * @return
     */
    public function __call($method, $arguments)
    {
        // 判断是否是异步发送
        if(0 === strpos($method, self::ASYNC_SEND_PREFIX))
        {
            $real_method = substr($method, strlen(self::ASYNC_SEND_PREFIX));
            $instance_key = $real_method . serialize($arguments);
            if(isset($this->asyncInstances[$instance_key]))
            {
                throw new Exception($this->rpcClass . "->$method(".implode(',', $arguments).") have already been called");
            }
            $this->asyncInstances[$instance_key] = clone $this;
            return $this->asyncInstances[$instance_key]->___send($real_method, $arguments);
        }
        // 如果是异步接受数据
        if(0 === strpos($method, self::ASYNC_RECV_PREFIX))
        {
            $real_method = substr($method, strlen(self::ASYNC_RECV_PREFIX));
            $instance_key = $real_method . serialize($arguments);
            if(!isset($this->asyncInstances[$instance_key]))
            {
                throw new Exception($this->rpcClass . "->asend_$real_method(".implode(',', $arguments).") have not been called");
            }
            $instance = $this->asyncInstances[$instance_key];
            unset($this->asyncInstances[$instance_key]);
            return $instance->___receive();
        }
        // 同步发送接收
        $this->___send($method, $arguments);
        return $this->___receive();
    }

    /**
     * 发送 RPC 请求.
     *
     * @param string $method    PRC 方法名称.
     * @param mixed  $arguments 方法参数.
     *
     * @throws Exception 抛出开发用的错误提示信息.
     *
     * @return mixed
     */
    public function ___send($method, $arguments)
    {
        $sign = $this->rpcSecret;

        $packet = array(
            'data' => json_encode(
                array(
                    'version' => '2.1',
                    'user' => $this->rpcUser,
                    'password' => md5($this->rpcUser . ':' . $this->rpcSecret),
                    'timestamp' => microtime(true),
                    'class' => $this->rpcClass,
                    'method' => $method,
                    'params' => $arguments,
                )
            ),
        );

        $config = self::config();
        $packet['signature'] = $this->encrypt($packet['data'], $config['rpc_secret_key']);

        $this->executionTimeStart = microtime(true);

        $this->openConnection();

        // 用 JSON 序列化请求数据
        if (!$data = json_encode($packet)) {
            throw new Exception('YTextRpcClient: Cannot serialize $data with json_encode');
        }

        // 压缩数据
        $command = 'RPC';

        // 发送 RPC 文本请求协议
        $buffer = sprintf("%d\n%s\n%d\n%s\n", strlen($command), $command, strlen($data), $data);
        if (!@fwrite($this->connection, $buffer)) {
            throw new Exception(sprintf('YTextRpcClient send: Network %s disconnected (%.3fs)', $this->rpcUri, $this->executionTime()));
        }
    }

    /**
     * 接收 RPC 结果.
     *
     * @throws Exception 抛出开发用的错误提示信息.
     *
     * @return mixed
     */
    public function ___receive()
    {
        $timeout = ceil($this->timeout - $this->executionTime());
        $timeout = $timeout > 0 ? $timeout : 1;
        @stream_set_timeout($this->connection, $timeout);
        // 读取 RPC 返回数据的长度信息
        if (!$length = @fgets($this->connection)) {
            throw new Exception(
                sprintf(
                    'YTextRpcClient receive: Network %s may timed out(%.3fs)',
                    $this->rpcUri,
                    $this->executionTime()
                )
            );
        }
        $length = trim($length);
        if (!preg_match('/^\d+$/', $length)) {
            throw new Exception(sprintf('YTextRpcClient receive: Got wrong protocol codes: %s', bin2hex($length)));
        }
        $length = 1 + $length; // 1 means \n

        @stream_set_timeout($this->connection, 1);
        // 读取 RPC 返回的具体数据
        $ctx = '';
        while (strlen($ctx) < $length && $this->executionTime() < $this->timeout) {
            $ctx .= fgets($this->connection);
        }

        $ctx = trim($ctx);

        $this->closeConnection();

        // 反序列化 JSON 数据并返回
        if ($ctx !== '') {
            if (self::$allowReturnObject) {
                $ctx = json_decode($ctx);
            } else {
                $ctx = json_decode($ctx, true);
            }
        }

        if (self::$allowReturnObject) {
            if (isset($ctx->exception) && is_object($ctx->exception)) {
                throw new Exception('RPC Exception: ' . var_export((array)$ctx->exception, true));
            }
        } else {
            if (isset($ctx['exception']) && is_array($ctx['exception'])) {
                throw new Exception('RPC Exception: ' . var_export($ctx['exception'], true));
            }
        }

        return $ctx;
    }

    /**
     * 计算 RPC 请求时间.
     *
     * @return float
     */
    private function executionTime()
    {
        return microtime(true) - $this->executionTimeStart;
    }

}

spl_autoload_register(
    function ($className) {
        if (strpos($className, 'RpcClient_') !== 0)
            return false;

        eval(sprintf('class %s extends \YClient\YTextRpcClient {}', $className));
    }
);

