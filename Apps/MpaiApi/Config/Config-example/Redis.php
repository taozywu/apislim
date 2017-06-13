<?php
/**
 * redis.
 *
 * @author taozywu <tao.wu@zerotech.com>
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
        array('master' => "10.25.76.133:7008", 'slave' => "10.25.76.133:7008"),
        ),
        'db' => 1
    );

}
