<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\core\memory;

use phspring\context\Ac;
use phspring\core\Bean;

/**
 * Class Pool
 * @package phspring\core\memory
 */
class Pool extends Bean
{
    /**
     * @var array [class1 => [Stack1], class2 => [Stack2]]
     */
    private $_map = [];

    /**
     * get one object from pool
     * @param string $class
     * @param array $definition
     * @return mixed
     */
    public function get($class, array $definition = [])
    {
        $pool = $this->_map[$class] ?? null;
        if ($pool === null) {
            $pool = $this->genPool($class);
        }
        if ($pool->count() > 0) {
            return $pool->shift();
        } else {
            $definition = array_merge($definition, [
                'gc' => [
                    'time' => time(),
                    'count' => 0
                ]
            ]);
            $clazz = new $class();
            foreach ($definition as $prop => $val) {
                $clazz->{$prop} = $val;
            }
            return $clazz;
        }
    }

    /**
     * recover a object to pool
     * @param string $class
     * @param Object $clazz
     */
    public function recover($clazz)
    {
        $class = get_class($clazz);
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
