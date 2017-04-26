<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\cache;

use phspring\core\Bean;

/**
 * class Cache
 * @package phspring\cache
 */
abstract class Cache extends Bean implements ICache
{
    /**
     * @var string key prefix
     */
    public $prefix = '';

    /**
     * @param $key
     */
    public function buildKey($key)
    {
        return $this->prefix . $key;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function get($key)
    {
        $key = $this->buildKey($key);
        $val = $this->doGet($key);

        return $val;
    }

    /**
     * @param $key
     * @param $val
     * @param int $expire
     * @return mixed
     */
    public function set($key, $val, $expire = 0)
    {
        $key = $this->buildKey($key);
        $res = $this->doSet($key, $val, $expire);

        return $res;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function mget(array $keys)
    {

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
     * @param $val
     */
    public function increment($key, $val = 1)
    {

    }

    /**
     * @param $key
     * @param $val
     */
    public function decrement($key, $val = 1)
    {

    }

    /**
     * @param $key
     * @return mixed
     */
    public function delete($key)
    {
        $key = $this->buildKey($key);
        $res = $this->doDelete($key);

        return $res;
    }

    /**
     * @return mixed
     */
    public function flush()
    {

    }

    /**
     * @param $key
     * @return mixed
     */
    abstract public function doGet($key);

    /**
     * @param $key
     * @param $val
     * @param int $expire
     * @return mixed
     */
    abstract public function doSet($key, $val, $expire = 0);

    /**
     * @param array $keys
     * @return mixed
     */
    abstract public function doMget(array $keys);

    /**
     * @param array $elements
     * @return mixed
     */
    abstract public function doMset(array $elements);

    /**
     * @param $key
     * @return mixed
     */
    abstract public function doDelete($key);

    /**
     * @param $key
     * @param $val
     * @return mixed
     */
    abstract public function doIncrement($key, $val);

    /**
     * @param $key
     * @param $val
     * @return mixed
     */
    abstract public function doDecrement($key, $val);

    /**
     * @return mixed
     */
    abstract public function doFlush();
}
