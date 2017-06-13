<?php
/**
 * session 管理
 * 可能有的问题： 没有对session的锁定
 */

namespace Library\Common;
use Library\Common\Redis\CRedis;

class YySession implements \ArrayAccess, \IteratorAggregate, \Countable
{

    private $_ttl;
    private static $_instance;
    private $_id;
    private $_redis;
    private $_oldSession;
    private $_newSession;

    /**
     * 单例.
     * 
     * @return JMSmartSession
     */
    public static function instance($config)
    {
        if (self::$_instance === null) {
            self::$_instance = new self($config);
        }
        return self::$_instance;
    }

    /**
     * 构造器.
     */
    private function __construct($config)
    {
        //设置session名
        ini_set("session.name",md5($config['name']));
        //设置session作用域
        ini_set('session.cookie_domain', $config['domain']);
        //防止cookie被恶意修改
        ini_set('session.cookie_httponly',1);
        //强制cookie存放sessionid
        ini_set('session.use_only_cookies',1);
        //禁止读取远程数据
        ini_set('allow_url_fopen',0);
        //禁止载入远程数据
        ini_set('allow_url_include',0);

        $this->_ttl = ini_get('session.gc_maxlifetime');
        session_set_save_handler(array($this, 'openSession'), array($this, 'closeSession'), array($this, 'readSession'), array($this, 'writeSession'), array($this, 'destroySession'), array($this, 'gcSession'));

        register_shutdown_function(array($this, 'close'));
        session_start();
        $this->_id = session_id();
        $_SESSION = $this;
        $key = $this->generateKey($this->_id);
    }
    
	public function close()
	{
        if ($this->_id !== null) {
            if ($this->_oldSession === $_SESSION) {
                $this->updateRedisTTL($this->generateKey($this->_id), $this->_ttl);
            } elseif (is_array($_SESSION)) { //如果没用$_SESSION,那么其一定是对象，此时不做任何动作
                $this->writeToRedis($this->generateKey($this->_id), $_SESSION, $this->_ttl);
            }
        } else {
            $this->updateRedisTTL($this->generateKey($this->_id), $this->_ttl);
        }
	}
    
    /**
     * 获取redis连接.
     * 
     * @return RedisMultiCache
     */
    public function getRedis()
    {
        if ($this->_redis === null) {
            $this->_redis = CRedis::cache('session');
        }
        return $this->_redis;
    }

    /**
     * 从redis获取session数据.
     * 
     * @param string $key Redis Key.
     * 
     * @return array
     */
    public function readFromRedis($key)
    {
        $data = $this->getRedis()->get($key);
        if ($data === false) {
            return array ();
        }
        $data = json_decode($data, true);
        if (is_array($data)) {
            return $data;
        }
        return array ();
    }

    /**
     * 写数据到redis.
     * 
     * @param string  $key   Redis Key.
     * @param string  $value Session值.
     * @param integer $ttl   有效期.
     */
    public function writeToRedis($key, $value, $ttl = 3600)
    {
        if (is_array($value)) {
            $this->getRedis()->setex($key, $ttl, json_encode($value));
        } else {
            $this->getRedis()->setex($key, $ttl, json_encode(array ()));
        }
    }

    /**
     * Session Handler 在此处不处理，由redis缓存时间决定.
     * 
     * @param string  $key   Redis Key.
     * @param integer $ttl   有效期.
     * 
     * @return boolean
     */
    public function updateRedisTTL($key, $ttl = 3600)
    {
        $this->getRedis()->expire($key, $ttl);
    }
    
    /**
     * 从redis里获取数据
     * 
     * @param unknown $key
     */
    public function getDataFromRedis($key)
    {
    	return $this->getRedis()->get($key);
    }

    /**
     * Session Handler 在此处不处理，由redis缓存时间决定.
     * 
     * @param integer $maxLifetime   有效期.
     * 
     * @return boolean
     */
    public function gcSession($maxLifetime)
    {
        return true;
    }

    /**
     * Session Handler 在此处不处理.
     * 
     * @param string $savePath      Session 存储路径.
     * @param string $sessionName   Session 名称.
     * 
     * @return boolean
     */
    public function openSession($savePath, $sessionName)
    {
        return true;
    }

    /**
     * Session Handler 保存session.
     * 
     * @return boolean
     */
    public function closeSession()
    {
        return true;
    }

    /**
     * Session Handler.
     * 
     * @param string $id  Session Id.
     * 
     * @return string
     */
    public function readSession($id)
    {
        return '';
    }

    /**
     * Session Handler.
     * 
     * @param string $id  Session Id.
     * @param string $data Session Data.
     * 
     * @return boolean
     */
    public function writeSession($id, $data)
    {
        return true;
    }

    /**
     * Session Handler session_destroy().
     * 
     * @param string $id Session Id.
     * 
     * @return boolean
     */
    public function destroySession($id)
    {
        $this->getRedis()->delete($this->generateKey($id));
        return true;
    }
    
    public function loadSession()
    {
        if ($this->_oldSession === null) {
        	$this->_oldSession = $this->_newSession = $this->readFromRedis($this->generateKey($this->_id));
        	$_SESSION = &$this->_newSession; //不加引入的话，如果empty($_SESSION['abc'])，如果abc已存在则PHP会死掉
        }
    }
    
    /**
     * 根据sessid获取session数据
     * 
     * @param unknown $sessid
     */
    public function getSession($sessid)
    {
    	$res = $this->getDataFromRedis($this->generateKey($sessid));
    	if (empty($res)) {
    		return array();
    	}
    	return $res;
    }
    
    /**
     * 延长session生命期
     *
     * @param unknown $sessid
     */
    public function extendSessionTtl($sessid)
    {
    	$key = $this->generateKey($sessid);
    	$ttl = $this->getRedis()->ttl($key);
    	if ($ttl === -2 || $ttl === -1) { // the key doesn't exist || the key has no ttl
    		return 0; // 已失效，需要重新登录, 返回-1的时间是很短的，直接让客户端重新登录吧
    	} else { // 延长失效期
    		$this->updateRedisTTL($key, $this->_ttl);
    		return 1;
    	}
    }
    
    /**
     * 生成Redis Key.
     * 
     * @param string $key Redis Key.
     * 
     * @return string
     */
    public function generateKey($key)
    {
        return 'PHPSESSION_'.$key;
    }
    
    
    /*****************************************************************/
    /* ArrayAccess Implementation */
    /*****************************************************************/
    
    /**
     * Offset to set
     *
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value The value to set.
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->loadSession();
    	if (is_null($offset)) {
    		$_SESSION[] = $value;
    	} else {
    		$_SESSION[$offset] = $value;
    	}
    }
    
    /**
     * Whether a offset exists
     *
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset An offset to check for.
     *
     * @return boolean true on success or false on failure.
     */
    public function offsetExists($offset)
    {
        $this->loadSession();
    	return isset($_SESSION[$offset]);
    }
    
    /**
     * Offset to unset
     *
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset The offset to unset.
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->loadSession();
    	unset($_SESSION[$offset]);
    }
    
    /**
     * Offset to retrieve
     *
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset The offset to retrieve.
     *
     * @return mixed Can return all value types.
     */
    public function &offsetGet($offset) 
    {
        $this->loadSession();
        return $_SESSION[$offset];
    }
    
    
    /*****************************************************************/
    /* IteratorAggregate Implementation */
    /*****************************************************************/
    
	public function getIterator()
    {
        $this->loadSession();
        return new \ArrayIterator($_SESSION);
    }
    
    
    /*****************************************************************/
    /* Countable Implementation */
    /*****************************************************************/
    
    /**
     * Get the count of elements in the container array.
     *
     * @link http://php.net/manual/en/countable.count.php
     *
     * @return int
     */
    public function count()
    {
    	$this->loadSession();
    	return count($_SESSION);
    }

}