<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\core;

use phspring\context\Context;

/**
 * Class BeanPool
 * @package phspring\core
 */
class BeanPool extends Bean implements IRecoverable
{
    /**
     * @var Context
     */
    public $context;

    /**
     * get context
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * set context
     * @return
     */
    public function setContext(Context $context)
    {
        $this->context = $context;
    }

    /**
     * bean pool clear
     * @return
     */
    public function scavenger()
    {
        unset($this->context);
    }
}
