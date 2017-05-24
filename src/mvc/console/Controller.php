<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\mvc\console;

/**
 * Class Controller
 * @package phspring\mvc\console
 */
class Controller extends \phspring\mvc\Controller
{
    /**
     * @var string
     */
    public $mode = 'console';

    /**
     * run action
     */
    public function runAction()
    {

    }

    /**
     * scavenger
     */
    public function scavenger()
    {
        $this->mode = 'console';
        parent::scavenger();
    }
}
