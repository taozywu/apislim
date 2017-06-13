<?php
namespace Common\Redis;

/**
 * 统一对外访问入口
 */
class CRedis
{
    /**
     * 存储方式调用
     * @param  string $endpoint [description]
     * @param  string $as       [description]
     * @return [type]           [description]
     */
    public static function storage($endpoint = 'default')
    {
        return RedisMultiStorage::getInstance($endpoint);
    }

    /**
     * 缓存调用
     * @param  string $endpoint [description]
     * @return [type]           [description]
     */
    public static function cache($endpoint = 'default')
    {
        return RedisMultiCache::getInstance($endpoint);
    }
}

