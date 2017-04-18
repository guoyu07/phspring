<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\core;

use phspring\exception\InvalidConfigException;

/**
 * Class BeanFactory
 * @package phspring\core
 */
class BeanFactory
{
    /**
     * singleton scope
     */
    CONST SCOPE_SINGLETON = 'singleton';
    /**
     * prototype scope
     */
    CONST SCOPE_PROTOTYPE = 'prototype';
    /**
     * request scope
     */
    CONST SCOPE_REQUEST = 'request';
    /**
     * pool scope
     */
    CONST SCOPE_POOL = 'pool';

    /**
     * @var array
     */
    private $_beans = [];
    /**
     * @var array
     */
    private $_singletons = [];
    /**
     * @var array
     */
    private $_prototypes = [];
    /**
     * @var array
     */
    private $_requests = [];
    /**
     * @var array
     */
    private $_pools = [];

    /**
     * init beans
     */
    public function initBeans(array $config)
    {
        foreach ($config as $name => $val) {
            if (!isset($val['class'])) {
                throw new InvalidConfigException('Bean class attribute is required.');
            }
            $this->setBean($name, $val);
        }
    }

    /**
     * Get a bean instance.
     */
    public function getBean($name)
    {
        if ($this->containsBean($name)) {
            $bean = $this->_beans[$name];
            if ($bean instanceof Bean && $bean->isSingleton) {
                return $this->_beans[$name];
            }
        }
    }

    /**
     * @param $name
     * @param $val
     */
    public function setBean($name, $val)
    {
        if (!isset($val['scope'])) {
            $val['scope'] = self::SCOPE_SINGLETON;
        }
        switch ($val['scope']) {
            case self::SCOPE_PROTOTYPE:
                break;
            case self::SCOPE_SINGLETON:
                if (!is_object($val)) {
                    $val = $this->createBean($val);
                }
                break;
            case self::SCOPE_REQUEST:
                break;
            case self::SCOPE_POOL:
                break;
        }

        $this->_beans[$name] = $val;
    }

    /**
     * create a bean.
     */
    public function createBean($class, array $params = [])
    {
        if (is_string($class)) {
            return $this->getBean($class, $params);
        } elseif (is_array($class) && isset($class['class'])) {
            return $this->getBean($class, $params);
        }
    }

    /**
     * Check if the bean exists.
     * @return bool
     */
    public function containsBean($name): bool
    {
        return array_key_exists($name, $this->_beans);
    }

    /**
     * @return bool
     */
    public function isSingleton($bean): bool
    {
        return $bean->scope === self::SCOPE_SINGLETON;
    }

    /**
     * @return bool
     */
    public function isPrototype($bean): bool
    {
        return $bean->scope === self::SCOPE_PROTOTYPE;
    }

    /**
     * @return bool
     */
    public function isRequest($bean): bool
    {
        return $bean->scope === self::SCOPE_REQUEST;
    }

    /**
     * @return bool
     */
    public function isPool($bean): bool
    {
        return $bean->scope === self::SCOPE_POOL;
    }
}
