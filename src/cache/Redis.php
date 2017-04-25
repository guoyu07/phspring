<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\cache;

/**
 * Class Redis
 * @package phspring\cache
 */
class Redis extends Cache implements ICache
{
    /**
     * @param $key
     * @return mixed
     */
    public function get($key)
    {

    }

    /**
     * @param $key
     * @param $value
     * @param int $expire
     * @return mixed
     */
    public function set($key, $value, $expire = 0)
    {

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