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
     * @var array
     */
    private $gc = [
        'count' => 0, // use count
        'time' => 0, // object gen time
    ];

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
     * @return array
     */
    final public function getGc()
    {
        return $this->gc;
    }

    /**
     * @var int $count
     */
    final public function incGcCount($count = 1)
    {
        $this->gc['count'] += $count;
    }

    /**
     * @param int $time
     */
    final public function setGcTime($time)
    {
        $this->gc['time'] = $time;
    }

    /**
     * bean pool clear
     * @return
     */
    public function scavenger()
    {
        $this->context = null;
    }

    /**
     * @param BeanPool $child
     * @param array $properties
     */
    public function resetProperties(BeanPool $child, array $properties = [])
    {
        if (empty($properties)) {
            return;
        }
        foreach ($properties as $prop => $val) {
            $child->{$prop} = $val;
        }
    }
}
