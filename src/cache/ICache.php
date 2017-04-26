<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\cache;

/**
 * Interface ICache
 * @package phspring\cache
 */
Interface ICache
{
    /**
     * @param $key
     * @return mixed
     */
    public function get($key);

    /**
     * @param $key
     * @param $val
     * @param int $expire
     * @return mixed
     */
    public function set($key, $val, $expire = 0);

    /**
     * @param $key
     * @return mixed
     */
    public function mget(array $keys);

    /**
     * @param array $elements
     * @return mixed
     */
    public function mset(array $elements);

    /**
     * @param $key
     * @return mixed
     */
    public function delete($key);

    /**
     * @return mixed
     */
    public function flush();
}
