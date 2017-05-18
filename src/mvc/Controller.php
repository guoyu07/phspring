<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\mvc;

/**
 * Class Controller
 * @package phspring\mvc
 */
class Controller extends Base
{
    /**
     * @var bool
     */
    public $isRpc = false;

    /**
     * run method
     */
    public function runMethod()
    {

    }

    /**
     * scavenger
     */
    public function scavenger()
    {
        $this->isRpc = false;
        parent::scavenger();
    }
}
