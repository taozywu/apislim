<?php
/**
 * AclConfig.
 *
 * @author taozywu <taozy.wu@qq.com>
 * @date 2016/10/20
 */
namespace MpaiApi\Config;

class AclConfig
{
    public $apps = array(
        'GET' => array(
            '/apps/version' => array(
                'v' => array("3.9.9.99" => 2),
            ),
        ),
        'POST' =>array(
        ),
        'PUT' => array(),
        'DELETE' =>array(),
    );
}
