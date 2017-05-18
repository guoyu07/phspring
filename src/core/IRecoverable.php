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
     * bean pool clear
     * @return
     */
    public function scavenger();
}
