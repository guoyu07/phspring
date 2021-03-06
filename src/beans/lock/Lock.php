<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\beans\lock;

use phspring\core\Bean;
use phspring\core\BeanFactory;

/**
 * Class Lock
 * @package phspring\beans\lock
 */
abstract class Lock extends Bean implements ILock
{
    /**
     * @param $data
     * @param string $key
     */
    public function acquire($data, $key = '')
    {

    }

    /**
     * @param $data
     * @param string $key
     */
    public function release($data, $key = '')
    {

    }

    /**
     * @param $data
     * @param $key
     * @return mixed
     */
    abstract public function doAcquire($data, $key);

    /**
     * @param $data
     * @param $key
     * @return mixed
     */
    abstract public function doRelease($data, $key);
}
