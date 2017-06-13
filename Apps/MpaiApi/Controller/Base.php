<?php
/**
 * 基础类.
 *
 * @author taozywu <tao.wu@zerotech.com>
 * @date 2016/10/20
 */
namespace MpaiApi\Controller;

use Rest;
use Respect\Validation\Validator as v;
/**
 * 基础类.
 */
class Base extends \Custom\Base
{

    /**
     * 构造函数.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 统一输出方法.
     */
    public function dump($code = 1, $result = null, $msg = null)
    {
        $code = (int) $code;
	    $lang = C("LANG");
        if ($code === 1) {
            $this->success($result, $code, $msg ? $msg : $lang[100000012]);
            exit;
        }
        $this->error($code, $result, $msg);
        unset($lang);
        exit;
    }

    /**
     * 成功.
     *
     * @param string $data 数据.
     * @param integer $code 错误码.
     *
     * @throws \Exception
     */
    protected function success($data = null, $code = 0, $msg = null)
    {
        $msg = $msg ? $msg : C("LANG.100000012");
        self::outPut($code, $data, $msg);
        exit;
    }

    /**
     * 失败.
     *
     * @param integer $code 错误码.
     * @param string $data 数据.
     *
     * @throws \Exception
     */
    protected function error($code, $data = null, $msg = null)
    {
        $lang = C("LANG");
        if (array_key_exists($code, $lang)) {
            self::outPut($code, $data, $msg ? $msg : $lang[$code]);
        } else {
            self::outPut($code, $data, $msg ? $msg : '错误信息获取失败');
        }
        unset($lang);
        exit;
    }

    /**
     * 兼容TP.
     * // get.demo => $_GET['demo']
     * // post.demo => $_POST['demo']
     * 
     * @param string $key Key.
     * 
     * @return mixed.
     */
    public function _getParam($key)
    {
        return I($key);
    }

    /**
     * 检查是否允许访问.
     *
     * @return bool
     */
    public function check()
    {
        
    }

    

}
