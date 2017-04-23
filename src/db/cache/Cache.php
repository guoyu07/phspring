<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\db\cache;

use phspring\core\Bean;

/**
 * class Cache
 * @package phspring\db\cache
 */
abstract class Cache extends Bean implements ICache
{
    /**
     * @var string
     */
    public $keyPrefix = '';

    /**
     * @param $key
     */
    public function buildKey($key)
    {
        return $this->keyPrefix . $key;
    }

    /**
     * @param $key
     * @return mixed
     */
    abstract public function get($key);

    /**
     * @param $key
     * @param $value
     * @param int $expire
     * @return mixed
     */
    abstract public function set($key, $value, $expire = 0);

    /**
     * @param $key
     * @return mixed
     */
    abstract public function mget(array $keys);

    /**
     * @param array $elements
     * @return mixed
     */
    abstract public function mset(array $elements);

    /**
     * @param $key
     * @return mixed
     */
    abstract public function delete($key);

    /**
     * @return mixed
     */
    abstract public function flush();
}
