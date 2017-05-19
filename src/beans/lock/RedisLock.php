<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\beans\lock;

/**
 * Class RedisLock
 * @package phspring\beans\lock
 */
class RedisLock extends Lock
{
    /**
     * @param $data
     * @param $key
     * @return mixed
     */
    public function doAcquire($data, $key)
    {

    }

    /**
     * @param $data
     * @param $key
     * @return mixed
     */
    public function doRelease($data, $key)
    {

    }
}
