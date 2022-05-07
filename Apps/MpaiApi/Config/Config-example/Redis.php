<?php
/**
 * redis.
 *
 * @author taozywu <taozy.wu@qq.com>
 * @date 2016/10/20
 */
namespace MpaiApi\Config;

/**
 * Config\MpaiApi Redis.
 */
class Redis
{
    /**
     * session.
     * @var array
     */
    public $default = array('nodes' => array(
        array('master' => "localhost:7008", 'slave' => "localhost:7008"),
        ),
        'db' => 1
    );

}
