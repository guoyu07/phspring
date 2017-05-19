<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\beans\lock;

/**
 * Interface ILock
 * @package phspring\beans\lock
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
