<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\core;

use phspring\context\Ac;
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
     * @var \phspring\core\BeanPool
     */
    public $beanPool = null;

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
    private $_reflections = [];
    /**
     * @var array
     */
    private $_refs = [];

    /**
     * BeanFactory constructor.
     * @param array $beans
     */
    public function __construct(array $beans = [])
    {
        $this->beanPool = new BeanPool();
        $this->initBeans($beans);
    }
    
    /**
     * init beans
     */
    public function initBeans(array $beans)
    {
        foreach ($beans as $name => $definition) {
            $definition = $this->formatDefinition($definition);
            $this->setBean($name, $definition);
        }
    }

    /**
     * @param $name
     * @param array $args constructor parameters.
     * @param array $definition class definition.
     * @return array|mixed
     * @throws \Exception
     */
    public function getBean($name, $args = [], $definition = [])
    {
        if (isset($this->_singletons[$name])) {
            return $this->_singletons[$name];
        }

        if (isset($this->_beans[$name])) {
            $config = $this->_beans[$name];
            if (is_array($config)) {
                $class = $config['class'];
                unset($config['class']);
                $config = array_merge($config, $definition);
                if ($class === $name) {
                    $clazz = $this->generate($class, $args, $config);
                } else {
                    $clazz = $this->getBean($class, $args, $config);
                }

                return $clazz;
            } else {
                throw new \Exception('Unexpected object definition type: ' . gettype($config));
            }
        }
        $this->setBean($name, $definition, $args);

        return $this->getBean($name, $args, $definition);
    }

    /**
     * @param string $name
     * @param array $definition
     * @param array $args
     * @return $this
     */
    public function setBean($name, $definition = [], $args = [])
    {
        $this->_beans[$name] = $this->formatDefinition($name, $definition);
        if ($definition['scope'] === self::SCOPE_SINGLETON) {
            $this->_singletons[$name] = $this->generate($this->_beans[$name]['class'], $args, $definition);
        }

        return $this;
    }

    /**
     * create a bean.
     */
    public function createBean($class, array $args = [])
    {
        if (is_string($class)) {
            return $this->getBean($class, $args);
        } elseif (is_array($class) && isset($class['class'])) {
            return $this->getBean($class, $args);
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
     * @param $name
     */
    public function clearBean($name)
    {
        unset($this->_beans[$name]);
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

    /**
     * @param string $name
     * @param mixed $definition
     * @return array|string
     * @throws InvalidConfigException
     */
    private function formatDefinition($name, $definition)
    {
        if (empty($definition)) {
            if (strpos($name, '\\') === false) {
                throw new InvalidConfigException('Bean class attribute is required.');
            }
            return ['name' => $name, 'scope' => self::SCOPE_POOL, 'class' => $name];
        } elseif (is_string($definition)) {
            if (strpos($definition, '\\') === false) {
                throw new InvalidConfigException('Bean class attribute is required.');
            }
            return ['name' => $name, 'scope' => self::SCOPE_POOL, 'class' => $definition];
        } elseif (is_object($definition)) {
            return $definition;
        } elseif (is_array($definition)) {
            if (!isset($definition['class'])) {
                if (strpos($name, '\\') === false) {
                    throw new InvalidConfigException('Bean class attribute is required.');
                } else {
                    $definition['class'] = $name;
                }
            }
            $definition['scope'] = $definition['scope'] ?? self::SCOPE_POOL;
            return $definition;
        }

        throw new InvalidConfigException('Bean class attribute is required.');
    }

    /**
     * @param string $class
     * @param array $args
     * @param mixed $config
     */
    private function generate($class, $args, $definition)
    {
        /* @var $reflection ReflectionClass */
        list ($reflection, $refs) = $this->getRefs($class);
        $refs = $this->resolveRefs($refs, $reflection);
        $scope = $definition['scope'];
        if ($scope == self::SCOPE_POOL) {
            $clazz = $this->beanPool->get($class);
        } elseif($scope === self::SCOPE_PROTOTYPE) {
            if (!$reflection->isInstantiable()) {
                throw new \Exception($reflection->name);
            }
            $diff = count($refs) - count($args);
            foreach ($args as $idx => $arg) {
                $refs[$diff + $idx] = $arg;
            }
            $clazz = $reflection->newInstanceArgs($refs);
        }
        if (!empty($definition)) {
            foreach ($definition as $prop => $val) {
                $clazz->{$prop} = $val;
            }
        }

        return $clazz;
    }

    /**
     * @param string $class
     * @return array
     */
    private function getRefs($class)
    {
        if (isset($this->_reflections[$class])) {
            return [$this->_reflections[$class], $this->_refs[$class]];
        }

        $refs = [];
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                if ($param->isDefaultValueAvailable()) {
                    $refs[] = $param->getDefaultValue();
                } else {
                    $constructClass = $param->getClass();
                    $refs[] = Instance::of($constructClass === null ? null : $constructClass->getName());
                }
            }
        }

        $this->_reflections[$class] = $reflection;
        $this->_refs[$class] = $refs;

        return [$reflection, $refs];
    }

    /**
     * @param array $refs
     * @param null $reflection
     * @return mixed
     * @throws \Exception
     */
    private function resolveRefs(array $refs, $reflection = null)
    {
        foreach ($refs as $idx => $ref) {
            if ($ref instanceof Instance) {
                if ($ref->name !== null) {
                    // Recursively get bean.
                    $refs[$idx] = $this->getBean($ref->name);
                } elseif ($reflection !== null) {
                    $name = $reflection->getConstructor()->getParameters()[$idx]->getName();
                    $class = $reflection->getName();
                    throw new \Exception("Missing required parameter \"$name\" when instantiating \"$class\".");
                }
            }
        }

        return $refs;
    }
}
