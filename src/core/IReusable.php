<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\core;

/**
 * Class IReusable
 * @package phspring\core
 */
interface IReusable
{
    /**
     * bean pool clear
     * @return
     */
    public function cleanup();
}
