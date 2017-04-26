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
     * encrypt
     */
    public function acquire($data, $key = '');

    /**
     * decrypt
     */
    public function release($data, $key = '');
}
