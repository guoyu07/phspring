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
    public $mode = 'web';

    /**
     * run action
     */
    public function runAction()
    {

    }

    /**
     * cleanup
     */
    public function cleanup()
    {
        $this->isRpc = false;
        parent::cleanup();
    }
}
