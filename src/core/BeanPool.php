<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\core;

/**
 * Class BeanPool
 * @package phspring\core
 */
class BeanPool extends Bean
{
    /**
     * @var array [class1 => [Stack1], class2 => [Stack2]]
     */
    private $_map = [];

    /**
     * get one object from pool
     * @param string $class
     * @return mixed
     */
    public function get($class)
    {
        $pool = $this->_map[$class] ?? null;
        if ($pool === null) {
            $pool = $this->genPool($class);
        }
        if ($pool->count() > 0) {
            return $pool->shift();
        } else {
            $clazz = new $class();
            $clazz->useCount = 0;
            $clazz->genTime = time();
            return $clazz;
        }
    }

    /**
     * revert a object to pool
     * @param string $class
     * @param Bean $clazz
     */
    public function revert($class, Bean $clazz)
    {
        $pool = $this->_map[$class] ?? null;
        if ($pool === null) {
            $pool = $this->genPool($class);
        }
        $pool->push($clazz);
    }

    /**
     * @param string $class
     */
    public function clear($class)
    {
        if (isset($this->_map[$class])) {
            unset($this->_map[$class]);
        }
    }

    /**
     * @param string $beanId
     * @return mixed
     * @throws \Exception
     */
    private function genPool($class)
    {
        if (array_key_exists($class, $this->_map)) {
            throw new \Exception('the name is exists in pool map');
        }
        $this->_map[$class] = new \SplStack();

        return $this->_map[$class];
    }
}
