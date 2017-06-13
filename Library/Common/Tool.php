<?php
/**
 * Tool
 * @author taozywu <taozy.wu@qq.com>
 * @date 2016/10/20
 */

namespace Common;


class Tool
{

    /**
     * 模块定义
     */
    const OBJECT_TYPE_ALL               = 0;  //所有
    const OBJECT_TYPE_USER              = 10; //用户


    /**
     * 获取模块类型
     * @return [type] [description]
     */
    public static function getOtype($otype)
    {
        return self::OBJECT_TYPE_USER;
    }


    /**
     * 检测签名.
     *
     * @param string $uri  连接地址.
     * @param array $params 参数.
     * @param string $sn   秘钥.
     *
     * @return string
     */
    public static function sign($uri, array $params, $sn) {
        ksort($params);
        $data = array();
        foreach($params as $key => $val){
            $data[] = $key.'='.$val;
        }
        $str = implode("&",$data);
        $result = md5($sn. $uri. $str. \Rest::$config['salt']);
        return $result;
    }

}
