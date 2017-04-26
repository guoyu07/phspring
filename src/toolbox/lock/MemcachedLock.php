<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\lock;

/**
 * Class MemcachedLock
 * @package phspring\toolbox\lock
 */
class MemcachedLock extends Lock
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
