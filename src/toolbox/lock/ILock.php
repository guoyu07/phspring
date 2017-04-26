<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\lock;

/**
 * Interface ILock
 * @package phspring\toolbox\lock
 */
interface ILock
{
    /**
     * acquire lock
     */
    public function acquire($data, $key = '');

    /**
     * release lock
     */
    public function release($data, $key = '');
}
