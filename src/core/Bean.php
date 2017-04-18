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
     * @var string
     */
    public $scope = 'singleton';
    /**
     * @var null
     */
    public $ref = null;

    public function __construct()
    {

    }

    public function init()
    {

    }

    public function __set()
    {

    }

    public function __get($name)
    {

    }

    public function __isset($name)
    {

    }

    public function __unset($name)
    {

    }

    public function __call($name, $arguments)
    {

    }

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

    public function canGetProp(): bool
    {

    }

    public function canSetProp(): bool
    {

    }

    /**
     * @param $method
     * @param Bean $bean
     * @return bool
     */
    public function hasMethod($method, Bean $bean): bool
    {
        return method_exists($bean, $method);
    }

    /**
     * clean a object
     */
    public function destory()
    {

    }
}
