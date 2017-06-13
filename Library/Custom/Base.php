<?php
/**
 * Class Base.
 *
 * @author taozywu <taozy.wu@qq.com>
 * @date 2016/10/20
 */
namespace Custom;

use Rest;

class Base
{
    /**
     * 验证类.
     *
     * @var null
     */
    protected $rule = array();
    /**
     * @var \Common\Safe\Security
     */
    protected $xssInst;
    /**
     * 验证数据时错误提示
     * @var array
     */
    public $errorMsg = array();
    /**
     * jsonp回调方法.
     *
     * @var string
     */
    protected static $callbackParam = '';

    /**
     * @var array 用户数据.
     */
    protected static $data = array();

    /**
     * 构造
     */
    public function __construct()
    {
        // YClient加载配置文件 update by taozywu | 2016/10/20
        \YClient\YTextRpcClient::config(Rest::$config['rpcserver']);
        // Xss实例
        $this->xssInst = new \Common\Safe\Security();
        // 500错误
        Rest::$slim->error(function ($e) {
            Rest::$slim->getLog()->error($e);
            $this->OutPut(100000010,null,'系统错误');
        });
        // 404错误
        Rest::$slim->notFound(function(){
            $this->OutPut(100000011,null,'页面没找到');
        });

    }
    /**
     *
     * 输出数据.
     *
     * @param integer $code 错误值.
     * @param null    $data 数据.
     * @param string  $msg  描述.
     *
     * @return null
     * @throws \Exception e
     */
    protected final static function OutPut($code, $data = null, $msg = '')
    {
        if ($code != 1) {
            $return = '';
            $info = explode('XXX',$msg);
            if(count($info) > 1){
                $return = array (
                    'status' => $code,
                    'tips' => $info[1],
                    'data'  => $data,
                );
            }else{
                $return = array (
                    'status' => $code,
                    'tips' => $msg,
                    'data'  => $data,
                );
            }
        } else {
            $return = array (
                'status' => 1,
                'tips' => $msg ? $msg : C("LANG.100000012"),
                'data'  => $data,
            );
        }
        
        $data = $data === null ? (object) $data : $data;
        $data = is_object($data) ? (object) $data : (is_array($data) ? (array) $data : "");
        $return['data'] = $data;
        // 设置头信息

        Rest::$slim->contentType('application/json; charset=utf-8');
        // 配置跨域获取数据
        if(array_key_exists('originUrl',Rest::$config)){
            if(Rest::$config['originUrl']){
                if($url = Rest::$slim->request->headers('origin')){
                    $urlArr = parse_url($url);
                    if(in_array($urlArr['host'],Rest::$config['originUrl'])){
                        header('Access-Control-Allow-Origin:'.$url);
                    }
                }

            }
            header('Access-Control-Max-Age: 1000');
            header('Access-Control-Allow-Headers: Authorization, client-token, X-Requested-With, user-agent, content-type');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE');
        }

        // 判断是否可以压缩
        $gzip = Rest::isGzip();
        $gzip = false;
        $gzip && ob_start('ob_gzhandler');
        if(self::$callbackParam){
            echo self::$callbackParam.'('.self::jsonEncode($return).')';
        }else{
            echo self::jsonEncode($return);
        }
        $gzip && ob_end_flush();
        Rest::$slim->stop();
    }

    /**
     * Json 编码.
     *
     * @param mixed $data 任何类型数据.
     *
     * @return string
     */
    public static function jsonEncode($data)
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Json 解码.
     *
     * @param string $jsonData Json数据.
     *
     * @return mixed
     */
    public static function jsonDecode($jsonData)
    {
        return json_decode($jsonData, true);
    }

    /**
     * 获取get参数.
     *
     * @param null $key     键值.
     *
     * @return array|mixed|null
     */
    protected final function get($key = null){
        if($key == null){
            $get = Rest::$slim->request->get();
            foreach($get as &$value){
                $this->xssInst->xss_clean($value);
            }
            return $get;
        }
        return $this->xssInst->xss_clean(Rest::$slim->request->get($key));
    }

    /**
     * 获取POST参数.
     *
     * @param null $key     键值.
     *
     * @return array|mixed|null
     */
    protected final function post($key = null){
        if($key == null){
            $post = Rest::$slim->request->post();
            foreach($post as &$value){
                $this->xssInst->xss_clean($value);
            }
            return $post;
        }
        return $this->xssInst->xss_clean(Rest::$slim->request->post($key));
    }

    /**
     * 获取PUT参数.
     *
     * @param null $key     键值.
     *
     * @return array|mixed|null
     */
    protected final function put($key = null){
        if($key == null){
            $put = Rest::$slim->request->put();
            foreach($put as &$value){
                $this->xssInst->xss_clean($value);
            }
            return $put;
        }
        return $this->xssInst->xss_clean(Rest::$slim->request->put($key));
    }

    /**
     * 获取DELETE参数.
     *
     * @param null $key     键值.
     *
     * @return array|mixed|null
     */
    protected final function delete($key = null){
        if($key == null){
            $delete = Rest::$slim->request->delete();
            foreach($delete as &$value){
                $this->xssInst->xss_clean($value);
            }
            return $delete;
        }
        return $this->xssInst->xss_clean(Rest::$slim->request->delete($key));
    }

    /**
     * 获取header参数.
     *
     * @param null $key     键值.
     *
     * @return array|mixed|null
     */
    protected final function header($key){
        return $this->xssInst->xss_clean(Rest::$slim->request->headers($key));
    }

    /**
     * 验证数据.
     *
     * @param string $key
     * @param string $value
     * @param null $default
     *
     * @return bool|null|string
     */
    protected function validate($key,$value,$default = null){

        if(!array_key_exists($key,$this->rule)){
            $this->errorMsg[$key] = '没有配置验证规则';
            return false;
        }
        try{
            if(is_string($value)){
                $newDara = trim($value);
            }else{
                $newDara = $value;
            }
            $this->rule[$key]->assert($newDara);
            return $newDara;
        }catch (\Respect\Validation\Exceptions\AllOfException $e){
            $this->errorMsg[$key] = $e->getMessage();
            if(is_null($default)){
                return false;
            }else{
                unset($this->errorMsg[$key]);
                return $default;
            }
        }
    }

    /**
     * place集中验证数据.
     *
     * @param array $param 参数eg:array('name'=>'string')字段＝>检查类型
     * @param string $methodType 请求类型eg:post,get,delete...
     *
     * @return array|string
     */
    protected function checkValidateParam(array $param,$methodType)
    {
        $res = array();
        foreach ($param as $k=>$v) {
            $this->rule[$k] = $this->rule[$v];
            $val = $this->$methodType($k);
            if ($v == 'json') {
                $res[$k] =  json_decode($this->validate($k,$val),true);
            } else {
                $res[$k] =  $this->validate($k,$val);
            }

            if ( $this->errorMsg ) {
                return $k.':'.$this->errorMsg[$k].'=='.$val;
                break;
            }
        }
        return $res;
    }

}
