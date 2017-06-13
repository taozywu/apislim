<?php

/**
 * Rest.
 *
 * @author taozywu <taozy.wu@qq.com>
 * @date 2016/10/20
 */
class Rest
{

    /**
     * @var null|\Slim\Slim 实例化slim.
     */
    public static $slim = null;

    /**
     * @var $config
     */
    public static $config = null;

    /**
     * [$header description]
     * @var null
     */
    public static $header = null;

    public function __construct()
    {
        // 实例化slim
        self::$slim = new \Slim\Slim(array('log.writer' => new \Custom\SystemLog()));
        // 错误默认配置
        self::setDefault();
        // 配置路径
        self::setAppInclude();
        // 加载各自模块的function文件
        self::setFunction();
        // 加载配置文件.
        self::setConfig();
        // session配置
        self::setSession();
        // bug配置
        self::setDebug();
        // log配置
        self::setLog();
        // 创建钩子
        self::setHook('\Custom\Hook', 1);
        // 检查是否有自定义hook
        self::checkHook();
    }

    /**
     * setAppInclude.
     */
    private static function setAppInclude()
    {
        // 设置节点
        defined('SITE') or define('SITE', self::domainNameNode());
        // 设置模板
        define('TEMPLATE_PATH', ROOT . "/Apps/" . SITE . "/View");
        // Public
	    define('__PUBLIC__', "/Public");
    }

    /**
     * setFunction.
     */
    private static function setFunction()
    {
        foreach (glob(ROOT . "/Apps/" . SITE . "/Common/*.php") as $infile) {
            require_once $infile;
        }
    }

    /**
     * setConfig
     */
    private static function setConfig()
    {
        $class = SITE . '\Config\Config';
        self::$config = (array)new $class + self::getDefaultConfig();
        C(self::$config);
        self::$header = getAllHeader();
    }

    /**
     * 运行.
     */
    public function run()
    {
        self::$slim->run();
    }

    /**
     * 获取redis地址.
     *
     * @param string $type 存储类型.
     * @param string $app  数据类型.
     *
     * @return array
     * @throws Exception
     */
    public static function getRedisConfig($app)
    {
        $class = SITE . '\Config\Redis';
        $config = (array)new $class;
        if (!array_key_exists($app, $config)) {
            throw new Exception('SITE:' . SITE . ' redis配置文件加载失败,'.var_export(array($app),true) );
        }
        return array(
            $app => $config[$app],
        );
    }

    /**
     * 启动session配置.
     * #@todo 应该需要redis
     */
    private static function setSession()
    {
        if (!empty(self::$config['session']) && self::$config['session']['start'] == 1) {
            // \Library\Common\YySession::instance(self::$config['session']);
        }
    }

    /**
     * 获取节点.
     *
     * @return mixed
     */
    private static function domainNameNode()
    {
        // 将所有非点的全部转为.
        $hostName = strtolower(self::$slim->request()->getHost());
        $hostName = str_replace(array("-"), ".", $hostName);
        $serverName = explode('.', $hostName);
        // 检测是否有超过2个点
        if (count($serverName) > 2) {
            $serverName = array_slice($serverName, 0, 2);
        }
        return str_replace(' ', '', ucwords(str_replace('.',' ',implode(' ', $serverName))));
    }

    /**
     * 注册hook.
     *
     * @param     $class
     * @param int $priority
     */
    private static function setHook($class, $priority = 10)
    {
        self::$slim->hook('slim.before', $class . '::before', $priority);
        self::$slim->hook('slim.before.router', $class . '::beforeRouter', $priority);
        self::$slim->hook('slim.before.dispatch', $class . '::beforeDispatch', $priority);
        self::$slim->hook('slim.after.dispatch', $class . '::afterDispatch', $priority);
        self::$slim->hook('slim.after.router', $class . '::afterRouter', $priority);
        self::$slim->hook('slim.after', $class . '::after', $priority);
    }

    /**
     * 检测是否可以压缩.
     *
     * @static :静态方法
     *
     * @Example:self::isGzip()
     *
     * @return bool|null
     */
    public static function isGzip()
    {
        if ( !array_key_exists('HTTP_ACCEPT', $_SERVER) ) {
            return false;
        }
        return $_SERVER['HTTP_ACCEPT'];
    }

    /**
     * bug 显示设置.
     *
     */
    private static function setDebug()
    {
        // 配置错误显示
        if (self::$config['debug']) {
            self::$slim->config('debug', false);
            ini_set('display_errors', 'Off');
            error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING ^ E_STRICT);
        } else {
            self::$slim->config('debug', false);
            ini_set('display_errors', 'Off'); // 关闭错误显示就够了
            error_reporting (E_ALL ^ E_NOTICE ^ E_WARNING ^ E_STRICT);
        }
    }

   /**
    * 设置配置文件.
    *
    */
    private static function setLog()
    {
        if (is_array(self::$config['slim'])) {
            foreach (self::$config['slim'] as $key => $val) {
                self::$slim->config($key, $val);
            }
        }
    }

    /**
     * 设置默认错误输出.
     *
     */
    private static function setDefault()
    {
        // 500错误
        self::$slim->error(function ($e) {
            self::$slim->getLog()->error($e);
            echo '{"code":10,"msg":"System error"}';
        });
        // 404错误
        self::$slim->notFound(function () {
            echo '{"code":11,"msg":"not found"}';
        });
    }

    /**
     * 设置默认配置.
     *
     * @return array
     */
    private static function getDefaultConfig()
    {
        return array (
            'debug'   => 1,
            'default' => array (
                'controller' => 'home',
            ),
        );
    }

    /**
     * 检测自定义钩子是否存在.
     * 
     * @return [type] [description]
     */
    private static function checkHook()
    {
        if ( is_file(ROOT . '/Apps/' . SITE . '/Controller/Hook.php') ) {
            self::setHook('\\'.SITE.'\\Controller\Hook', 2);
        }
    }

    /**
     * 自动加载.
     *
     * @param string $className 加载文件类.
     *
     * @throws Exception
     */
    public static function autoload($className)
    {
        $path = DIRECTORY_SEPARATOR . ltrim(str_replace('\\', DIRECTORY_SEPARATOR, $className), DIRECTORY_SEPARATOR);
        $filePath = "";
        # controller
        if ( is_file(ROOT . "/Apps" . $path . '.php') ) {
            $filePath = ROOT . "/Apps" . $path . '.php';
        # library
        } elseif ( is_file(ROOT . '/Library' . $path . '.php') ) {
            $filePath = ROOT . '/Library' . $path . '.php';
        }
        // 判断文件是否存在
        if (!empty($filePath) && file_exists($filePath)) {
            require_once $filePath;
        }
    }
}

spl_autoload_register("Rest::autoload");
