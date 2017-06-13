<?php
/**
 * 基础类{迁移美拍接口的基类、后面会在考虑token的处理，暂时继续使用闫神的这种处理}.
 *
 * @author taozywu <taozy.wu@qq.com>
 * @date 2016/10/20
 */
namespace MpaiApi\Controller;

use Rest;
/**
 * 基础类.
 */
class BaseTp extends Base
{
    /**
     * 构造函数.
     */
    public function __construct()
    {
        parent::__construct();
        $this->getSession();
        $this->makeSign();
    }

    /**
     * 获取token.
     * @return [type] [description]
     */
    protected function getSession()
    {
        $sessid = json_encode($_SERVER).uniqid();
        $sessid = substr(md5($sessid.rand(1000000, 9999999).time()),8,16).substr(md5($sessid.rand(10000000, 99999999).time().rand(10000000, 99999999)),8,10);
        $json['sessid'] = $sessid;
        $sessionjson = json_encode($json);
        header("sessid:$sessionjson");
    }

    /**
     * 签名校验.
     * @return [type] [description]
     */
    protected function makeSign()
    {
        $getData = $this->get();
        unset($getData['sign']);
        unset($getData['time']);
        unset($getData['v']);
        $postData = $this->post();
        unset($postData['sign']);
        unset($postData['time']);
        unset($postData['v']);
        if(!$getData&&!$postData){
        }else{
            if($getData){
                $signData = $getData;
                $signtime = I('get.time');
                $signstr = I('get.sign');
                $signstr = isset($_GET['sign'])?$_GET['sign']:'';
            }else if($postData){
                $signData = $postData;
                $postData['data'] = isset($postData['data'])?$postData['data']:"";
                if($postData['data']){
                  $signData = stripslashes($postData['data']);
                }
                $signtime = I('post.time');
                $signstr = I('post.sign');
                $signstr = isset($_POST['sign'])?$_POST['sign']:"";
            }

            if(!is_array($signData)){
                return true;
            }
            ksort($signData);
            $sign = '';
            foreach ($signData as $k => $v) {
                if(!trim($v)){
                    unset($getData[$k]);
                }
                $sign .= htmlspecialchars_decode($v); //转义
            }
            if(!trim($sign)){
                return true;
            }

            $sign .= 'd318faa46ba217f51f6f583f714dccf4';
            $sign = strtoupper(md5($sign));
            $signstr = strtoupper($signstr);
            if ($sign != $signstr) {
                $this->dump(104,'','签名不一致');
            }
        }
    }

}
