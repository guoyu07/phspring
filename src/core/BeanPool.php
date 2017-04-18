<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\core;

use phspring\context\Ac;

/**
 * Class BeanPool
 * @package phspring\core
 */
class BeanPool extends Bean
{
    /**
     * @var array
     */
    private $map = [];

    /**
     * get one
     * @param string $name
     * @param string $class
     * @return mixed
     */
    public function get($name, $class)
    {
        $pool = $this->map[$name] ?? null;
        if ($pool === null) {
            $pool = $this->genPool($name);
        }
        if ($pool->count() > 0) {
            return $pool->shift();
        } else {
            $clazz = Ac::getBean($name);
            $clazz->useCount = 0;
            $clazz->genTime = time();
            return $clazz;
        }
    }

    /**
     * 返还一个对象
     * @param string $beanId
     * @param Bean $clazz
     */
    public function revert($name, Bean $clazz)
    {
        $pool = $this->map[$name] ?? null;
        if ($pool === null) {
            $pool = $this->genPool($name);
        }
        $pool->push($clazz);
    }

    /**
     * @param string $beanId
     * @return mixed
     * @throws \Exception
     */
    private function genPool($name)
    {
        if (array_key_exists($name, $this->map)) {
            throw new \Exception('the name is exists in pool map');
        }
        $this->map[$name] = new \SplStack();

        return $this->map[$name];
    }
}
