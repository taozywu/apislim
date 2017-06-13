<?php
/**
 * Config.
 *
 * @author taozywu <taozy.wu@qq.com>
 * @date 2016/10/20
 */
namespace MpaiApi\Config;

class Config
{
    public function __construct()
    {
        
    }

    // 调试
    public $debug = 1;

    // 过滤 @todo 兼容TP
    public $DEFAULT_DECODE_FILTER = "htmlspecialchars_decode,stripslashes";
    public $DEFAULT_FILTER = "trim,addslashes,htmlspecialchars";

    // slim 配置
    public $slim = array(
        'log.level' => \Slim\Log::DEBUG,
        'log.enabled' => true,
        "templates.path" => TEMPLATE_PATH
    );

    // 配置跨域服务地址
    public $originUrl = array(
        "test.com"
    );

    // debug允许的host
    public $hostsDebug  = array (
        '127.0.0.1'
    );

    // session 配置.
    public $session = array(
        'start' => 0,
        'domain' => 'test.com',
        'name' => 'h5_PHPSESSID'
    );

    // 固定盐
    public $salt = 'test';

    // mpai接口地址
    public $epUrl = 'http://test.com';

    // mpai接口H5地址
    public $h5Url = "http://test.com";

    // H5默认参数
    public $h5Params = array(
        # token
        "token" => "test",
        # 动态密钥
        "sn" => "",
    );

    // H5访问不走base的签名校验.
    public $allowH5Url = array(
        "test.com"
    );

    // 不需要走签名接口
    public $noSignApiList = array(
    );

    // token 登录最大失效期{60*60*24*30}
    public $tokenExpire = 2592000;

    // 获取pushstat数据的时间间隔(s)
    public $pushStatTime = 30;

    /**
     * rpcserver
     * @var array
     */
    public $rpcserver = array (
        'rpc_secret_key' => 'sa1238leeioopp9901wwwaabbcc',
        // 美拍
        'ZeroMpai'  => array (
            'uri'    => 'tcp://127.0.0.1:6201', # 负载
            'user'   => 'ServiceMpai',
            'secret' => '{1UA09530-F9U3-419D-9065-7EB31N595O7E}',
        ),
	    // 通知
        'ZeroNotice'  => array (
            'uri'    => 'tcp://127.0.0.1:6202',
            'user'   => 'ServiceMpai',
            'secret' => '{1UA09530-F9U3-419D-9065-7EB31N595O7E}',
        ),
    );

    /**
     * 区分1代接口,设置的分界线
     * 
     * @var string
     */
    public $NEW_API_VERSION = "2.0.0";

}
