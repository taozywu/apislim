<?php
namespace Common\Redis;

/*
 * 如果你在一个项目里面用到了很多个集群，那么用这个
 */

/**
 * Description of RedisMultiStorage
 *
 */
class RedisMultiCache {

    public static $instance;
    public static $config;

    public static function getInstance($name)
    {
        if (!self::$config) {
            self::$config = \Rest::getRedisConfig($name);
        }
        if (!isset(self::$instance[$name])) {
        	//var_dump(self::$config);die;
            RedisCache::config(self::$config,  $name);
            self::$instance[$name] = RedisCache::getInstance($name);
        }
        return self::$instance[$name];
    }

    public static function config(array $config)
    {
        self::$config = $config;
    }

    public static function close()
    {
        foreach ((array)self::$instance as $inst) {
            $inst->close();
        }
        self::$instance = array();
    }

}
