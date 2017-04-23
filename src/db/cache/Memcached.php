<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\db\cache;

/**
 * Class Memcached
 * @package phspring\db\cache
 */
class Memcached extends Cache implements ICache
{
    /**
     * @var array
     */
    public $servers = [];

    /**
     * init
     */
    public function init()
    {

    }

    /**
     * @param $key
     * @return mixed
     */
    public function get($key)
    {
        $key = $this->buildKey($key);

        return $this->_cache->get($key);
    }

    /**
     * @param $key
     * @param $value
     * @param int $expire
     * @return bool
     */
    public function set($key, $value, $expire = 0)
    {
        $key = $this->buildKey($key);

        return $this->_cache->set($key, $value, $expire);
    }

    /**
     * @param $key
     * @return mixed
     */
    public function mget(array $keys)
    {
        foreach ($keys as &$key) {
            $key = $this->buildKey($key);
        }
    }

    /**
     * @param array $elements
     * @return mixed
     */
    public function mset(array $elements)
    {

    }

    /**
     * @param $key
     * @return mixed
     */
    public function delete($key)
    {

    }

    /**
     * @return mixed
     */
    public function flush()
    {

    }
}
