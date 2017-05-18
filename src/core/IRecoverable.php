<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\core;

use phspring\context\Context;

/**
 * Class IRecoverable
 * @package phspring\core
 */
interface IRecoverable
{
    /**
     * get context
     * @return Context
     */
    public function getContext(): Context;

    /**
     * set context
     * @return
     */
    public function setContext();

    /**
     * bean pool clear
     * @return
     */
    public function scavenger();
}
