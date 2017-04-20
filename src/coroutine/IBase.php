<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\coroutine;

/**
 * Class IBase
 * @package phspring\coroutine
 */
interface IBase
{
    /**
     * @return mixed
     */
    public function isTimeout();

    /**
     * @param callable $callback
     * @return mixed
     */
    public function send(callable $callback);

    /**
     * @return mixed
     */
    public function getResult();
}