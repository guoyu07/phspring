<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\core;

/**
 * Class Bean
 * @package phspring\core
 */
class Bean
{
    /**
     * singleton/prototype/request/pool
     * @var string
     */
    public $scope = 'singleton';
    /**
     * @var null
     */
    public $ref = null;

    /**
     * @param $name
     * @param $val
     * @return mixed
     * @throws \Exception
     */
    public function __set($name, $val)
    {
        $setter = 'set' . $name;
        if (method_exists($this, $setter)) {
            return $this->$setter($val);
        }

        throw new \Exception('Setting unknown property: ' . get_class($this) . '::' . $name);
    }

    /**
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function __get($name)
    {
        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->$getter();
        }

        throw new \Exception('Getting unknown property: ' . get_class($this) . '::' . $name);
    }

    /**
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->$getter() !== null;
        }

        return false;
    }

    /**
     * @param $name
     * @throws \Exception
     */
    public function __unset($name)
    {
        $setter = 'set' . $name;
        if (method_exists($this, $setter)) {
            $this->$setter(null);
            return;
        }

        throw new \Exception('Unsetting an unknown or read-only property: ' . get_class($this) . '::' . $name);
    }

    /**
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {

    }

    /**
     * clone trigger
     */
    public function __clone()
    {

    }

    /**
     * @param $bean
     * @param $prop
     * @return bool
     */
    public function hasProp($bean, $prop)
    {
        return property_exists($bean, $prop);
    }

    /**
     * @return bool
     */
    public function canGetProp(): bool
    {

    }

    /**
     * @return bool
     */
    public function canSetProp(): bool
    {

    }

    /**
     * @param $method
     * @param Bean $bean
     * @return bool
     */
    public function hasMethod($method): bool
    {
        return method_exists($this, $method);
    }
}
