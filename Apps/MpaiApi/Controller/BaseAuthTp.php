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
class BaseAuthTp extends BaseTp
{
    public static $_data = null;
    /**
     * 构造
     */
    public function __construct()
    {
        parent::__construct();
        $this->isLogin();
        $this->getUserData();
        $this->setUserLang();
    }

    /**
     * 设置用户语言
     */
    private function setUserLang()
    {
        
    }

    /**
     * 获取用户信息并保存下.
     * @return [type] [description]
     */
    private function getUserData()
    {
        self::$_data = array(

        );
    }

    /**
     * 是否登录
     * @return boolean [description]
     */
    private function isLogin()
    {
        $sessid = isset(Rest::$header['sendsessid']) ? Rest::$header['sendsessid'] : '';
        if (!$sessid) {
            $this->dump(109, "", "没有头信息");
        }
        $map['sessid'] = $sessid;
        # 这里直接读主库
        $arr = \YClient\Text::inst("ZeroMpai")->setClass("Token")->getWriteToken($map);
        if (!$arr) {
            if (C("ISNEWVERSION")) {
                $this->dump(110, "", "没有登录或头和登录头不一致");
            }
            $this->dump(0, "", "");
        }
        # 已登录
        if ($arr['status'] == 2) {
            $this->dump(106, "", "您的账户已经在别处登录");
        }
        # 成功 60*60*24*30
        if ($arr['status'] == 1) {
            $time = time();
            if ($arr['create_time'] + C("tokenExpire") >= $time) {
                // 修改时间.
                \YClient\Text::inst("ZeroMpai")->setClass("Token")->save(array("create_time" => $time), $map);
                session('uid', $arr['uid']);
            }else{
                $this->dump(107, "", "登录时间失效过期");
            }
        } else {
            if (C("ISNEWVERSION")) {
                   $this->dump(111, "", "您的账户没有登录");
            }
            $this->dump(0, "", "您的账户没有登录");
        }
    }
}
