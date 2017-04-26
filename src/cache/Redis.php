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
    public function doGet($key)
    {

    }

    /**
     * @param $key
     * @param $val
     * @param int $expire
     * @return mixed
     */
    public function doSet($key, $val, $expire = 0)
    {

    }

    /**
     * @param $key
     * @return mixed
     */
    public function doMget(array $keys)
    {

    }

    /**
     * @param array $elements
     * @return mixed
     */
    public function doMset(array $elements)
    {

    }

    /**
     * @param $key
     * @return mixed
     */
    public function doDelete($key)
    {

    }

    /**
     * @return mixed
     */
    public function doFlush()
    {

    }
}
