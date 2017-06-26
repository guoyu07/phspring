<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\core;

use phspring\context\Context;
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
    //CONST SCOPE_REQUEST = 'request';
    /**
     * pool scope
     */
    CONST SCOPE_POOL = 'pool';

    /**
     * @var \phspring\core\PoolBean
     */
    public $poolBean = null;

    /**
     * @var array [name1 => ['class' => '\\a\\b', 'scope' => 'pool', 'prop1' => 1]]
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
        $this->poolBean = new PoolBean();
        $this->initBeans($beans);
    }

    /**
     * init beans
     */
    public function initBeans(array $beans)
    {
        foreach ($beans as $name => $option) {
            // cannot pool scope.
            $this->setBean($name, $option);
        }
    }

    /**
     * @param string $name
     * @param Context $context
     * @param array $args constructor parameters.
     * @param array|string $option class definition.
     * @return object
     * @throws \Exception
     */
    public function getBean($name, Context $context = null, array $args = [], $option = [])
    {
        if (isset($this->_singletons[$name])) {
            return $this->_singletons[$name];
        }
        if (!isset($this->_beans[$name])) {
            $this->setBean($name, $context, $option, $args);
        }

        $config = $this->_beans[$name];
        if (is_array($config)) {
            $class = $config['class'];
            unset($config['class']);
            $config = array_merge($config, $option);
            $clazz = $this->generate($class, $context, $args, $config);

            return $clazz;
        } else {
            throw new \Exception('Unexpected object definition type: ' . gettype($config));
        }
    }

    /**
     * @param string $name
     * @param array|string $option
     * @param array $args
     * @return $this
     */
    public function setBean($name, Context $context = null, $option = [], array $args = [])
    {
        $this->_beans[$name] = $this->formatDefinition($name, $option);
        //var_dump($this->_beans);
        if ($option['scope'] === self::SCOPE_SINGLETON) {
            $this->_singletons[$name] = $this->generate($this->_beans[$name]['class'], $context, $args, $option);
        }

        return $this;
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
     * @param Context|null $context
     */
    public function clearBean($name, Context $context = null)
    {
        if (isset($this->_beans[$name])) {
            $scope = $this->_beans[$name]['scope'];
            if ($scope == self::SCOPE_POOL && $context) {
                $context->pool->clear($name);
            } elseif ($scope == self::SCOPE_SINGLETON) {
                unset($this->_singletons[$name]);
            }
            unset($this->_beans[$name]);
        }
    }

    /**
     * @param $name
     * @return false | string
     */
    public function getScope($name)
    {
        if (!isset($this->_beans[$name])) {
            return false;
        }

        return $this->_beans[$name]['scope'];
    }

    /**
     * @param string $name
     * @param mixed $option
     * @return array|string
     * @throws InvalidConfigException
     */
    private function formatDefinition($name, $option)
    {
        if (empty($option)) {
            if (strpos($name, '\\') === false) {
                throw new InvalidConfigException('Bean class attribute is required.');
            }
            return ['name' => $name, 'scope' => self::SCOPE_POOL, 'class' => $name];
        } elseif (is_string($option)) {
            if (strpos($option, '\\') === false) {
                throw new InvalidConfigException('Bean class attribute is required.');
            }
            return ['name' => $name, 'scope' => self::SCOPE_POOL, 'class' => $option];
        } elseif (is_array($option)) {
            if (!isset($option['class'])) {
                if (strpos($name, '\\') === false) {
                    throw new InvalidConfigException('Bean class attribute is required.');
                } else {
                    $option['class'] = $name;
                }
            }
            $option['scope'] = $option['scope'] ?? self::SCOPE_POOL;
            return $option;
        }

        throw new InvalidConfigException('Bean class attribute is required.');
    }

    /**
     * @param string $class Class name
     * @param Context $context
     * @param array $args Class construct parameters
     * @param array $option Class define parameters
     * @return  Object
     */
    private function generate($class, Context $context = null, array $args, array $option)
    {
        /* @var $reflection ReflectionClass */
        list ($reflection, $refs) = $this->getRefs($class);
        $refs = $this->resolveRefs($refs, $reflection, $context);

        $scope = $option['scope'];
        if ($scope == self::SCOPE_POOL) {
            if ($context === null) {
                throw new \Exception('Generate bean failed, because context is null.');
            }
            return $context->pool->get($class, $option);
        } elseif ($scope == self::SCOPE_SINGLETON || $scope === self::SCOPE_PROTOTYPE) {
            if (!$reflection->isInstantiable()) {
                throw new \Exception($reflection->name);
            }
            $diff = count($refs) - count($args);
            foreach ($args as $idx => $arg) {
                $refs[$diff + $idx] = $arg;
            }
            $clazz = $reflection->newInstanceArgs($refs);
        } else {
            throw new \Exception('Generate bean failed.');
        }

        if (!empty($option)) {
            unset($option['class']);
            foreach ($option as $prop => $val) {
                //var_dump('prop=' . $prop);
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
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                if ($param->isDefaultValueAvailable()) { // has default value
                    $refs[] = $param->getDefaultValue();
                } else {
                    $constructClass = $param->getClass();
                    $refs[] = Instance::of($constructClass === null ? null : $constructClass->getName());
                }
            }
        }
        // caching reflect data.
        $this->_reflections[$class] = $reflection;
        $this->_refs[$class] = $refs;

        return [$reflection, $refs];
    }

    /**
     * @param array $refs
     * @param null $reflection
     * @return array [0 => Ojbect1, 1 => Ojbect2]
     * @throws \Exception
     */
    private function resolveRefs(array $refs, $reflection = null, Context $context = null)
    {
        foreach ($refs as $idx => $ref) {
            if ($ref instanceof Instance) {
                if ($ref->name !== null) {
                    // Recursively get bean.
                    $refs[$idx] = $this->getBean($ref->name, $context);
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
