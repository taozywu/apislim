<?php
namespace Common\Redis;

/**
 *
 */
abstract class RedisBase {

    const STRING = 1;
    const SET = 2;
    const LISTS = 3;
    const ZSET = 4;
    const HASH = 5;

    /*
     * 是否是事务    
     */

    public  $isTransaction = false;

    /*
     * 是事务key和方法緩存    
     */
    public  $TransactionCache = array();

    /*
     * 所有的读操作
     */
    protected $ReadFun = array(
    );
    /*
     * 所有的写操作
     */
    protected $WriteFun = array(
    );

    /*
     * 暂时不支持的函数
     */
    protected $DisableFun = array(
        "KEYS", "BLPOP", "MSETNX", "BRPOP", "RPOPLPUSH", "BRPOPLPUSH", "SMOVE", "SINTER", "SINTERSTORE", "SUNION", "SUNIONSTORE", "SDIFF", "SDIFFSTORE", "ZINTER", "ZUNION",
        "FLUSHDB", "FLUSHALL", "RANDOMKEY", "SELECT", "MOVE", "RENAMENX", "DBSIZE", "BGREWRITEAOF", "SLAVEOF", "SAVE", "BGSAVE", "LASTSAVE"
    );

    /*
     * 本次调用的具体物理机,用于调试
     */
    protected $target = '';


    public function __call($name, $arguments) {
        if ($this->isTransaction) {//事務緩存
            $this->TransactionCache[] = array('name' => $name, 'arg' => $arguments);
            return true;
        }
        if (in_array(strtoupper($name), $this->DisableFun)) {
            throw new Exception("call the disable function!");
        }
        $obj = $this->ConnectTarget($arguments[0]);
        if (empty($obj)) {//节点失效了，但是ping还没踢掉呢
            return false;
        }
        return call_user_func_array(array($obj, $name), $arguments);
    }

    public function MULTI() {
        $this->isTransaction = true;
        return true;
    }

    public function EXEC() {
        $this->isTransaction = false;
        $key = null;
        foreach ((array) $this->TransactionCache as $cache) {//check key
            $arguments = $cache['arg'];
            if (strcmp($key, $arguments[0]) !== 0 && isset($key)) {
                throw new Exception("Transaction error!Need same key but multi key passed");
            }
            $key = $arguments[0];
        }
        $obj = $this->ConnectTarget($key);
        $obj->MULTI();
        foreach ((array) $this->TransactionCache as $cache) {
            call_user_func_array(array($obj, $cache['name']), $cache['arg']);
        }
        unset($this->TransactionCache);
        return $obj->EXEC();
    }

    /*
     * 分布式缓存需要特殊处理
     * 尽量少用,可以用集合代替呀
     */

    public function Mget(array $keys) {
        $ret = array();
        foreach ($keys as $key) {
            $obj = $this->ConnectTarget($key); //返回redis对象
            if (!$obj)//链接失败
                continue;
            $ret[] = $obj->get($key);
        }
        return $ret;
    }

    public function getMultiple(array $keys) {
        return $this->Mget($keys);
    }

    public function Mset(array $KeyValue) {
        $ObjValue = array();
        $ObjArr = array(); //对象数组
        foreach ($KeyValue as $key => $value) {
            $obj = $this->ConnectTarget($key); //返回redis对象
            if (!$obj)//链接失败
                continue;
            $ObjArr[(int) $obj->socket] = $obj;
            $ObjValue[(int) $obj->socket][$key] = $value;
        }
        foreach ($ObjValue as $socket => $kv) {
            $obj = $ObjArr[$socket];
            if (!$obj->mset($kv)) {
                return false;
            };
        }
        return true;
    }

    public function delete($key) {
        if (is_array($key)) {
            foreach ($key as $k) {
                $redis = $this->ConnectTarget($k);
                if (!$redis)//链接失败
                    continue;
                $redis->delete($k);
            }
        } else {
            $redis = $this->ConnectTarget($key); //返回redis对象
            $redis->delete($key);
        }
        return true;
    }


    public function GetTarget() {
        return $this->target;
    }

    /*
     * rename前端app用
     * $key1 原key
     * $key2 生成的新key
     * return 原来key的值
     */

    public function rename($key1, $key2) {
        $redis = $this->ConnectTarget($key1);
        $redisTarget = $this->ConnectTarget($key2);
        if ((int) $redis->socket === (int) $redisTarget->socket) {//key1,key2刚好在一台机器
            return $redis->rename($key1, $key2);
        }
        $type = $redis->type($key1);
        switch ($type) {
            case self::STRING:
                return $this->renameString($key1, $key2);
            case self::SET:
                return $this->renameSet($key1, $key2);
            case self::LISTS:
                return $this->renameList($key1, $key2);
            case self::ZSET:
                return $this->renameZSet($key1, $key2);
            case self::HASH:
                return $this->renameHash($key1, $key2);
            default:
                return false;
        }
    }

    private function renameString($key1, $key2) {
        $redis = $this->ConnectTarget($key1);
        $data = $redis->get($key1);
        if ($data !== false) {
            $redisTarget = $this->ConnectTarget($key2);
            $redisTarget->delete($key2);
            if ($redisTarget->set($key2, $data) === FALSE) {
                return false;
            }
        } else {
            return false;
        }
        $redis->delete($key1);
        return true;
    }

    private function renameSet($key1, $key2) {
        $redis = $this->ConnectTarget($key1);
        $data = $redis->sMembers($key1);
        if ($data) {
            $redisTarget = $this->ConnectTarget($key2);
            $redisTarget->delete($key2);
            foreach ($data as $value) {
                if ($redisTarget->sadd($key2, $value) === FALSE) {
                    return FALSE;
                }
            }
        } else {
            return FALSE;
        }
        $redis->delete($key1);
        return true;
    }

    private function renameList($key1, $key2) {
        $redis = $this->ConnectTarget($key1);
        $data = $redis->lRange($key1, 0, -1);
        if ($data) {
            $redisTarget = $this->ConnectTarget($key2);
            $redisTarget->delete($key2);
            foreach ($data as $value) {
                if ($redisTarget->rPush($key2, $value) === FALSE) {
                    return false;
                }
            }
        } else {
            return FALSE;
        }
        $redis->delete($key1);
        return true;
    }

    private function renameZSet($key1, $key2) {
        $redis = $this->ConnectTarget($key1);
        $data = $redis->zRange($key1, 0, -1, true);
        if ($data) {
            $redisTarget = $this->ConnectTarget($key2);
            $redisTarget->delete($key2);
            foreach ($data as $value => $score) {
                if ($redisTarget->zadd($key2, $score, $value) === FALSE) {
                    return FALSE;
                }
            }
        } else {
            return FALSE;
        }
        $redis->delete($key1);
        return true;
    }

    private function renameHash($key1, $key2) {
        $redis = $this->ConnectTarget($key1);
        $data = $redis->hGetAll($key1);
        if ($data) {
            $redisTarget = $this->ConnectTarget($key2);
            $redisTarget->delete($key2);
            if ($redisTarget->hMset($key2, $data) === FALSE) {
                return FALSE;
            }
        } else {
            return FALSE;
        }
        $redis->delete($key1);
        return true;
    }

    abstract public function ConnectTarget($key); //redis对象池

    abstract public function Init();
}

