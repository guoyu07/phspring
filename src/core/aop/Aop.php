<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\core\aop;

/**
 * Class Aop
 * @package phspring\core\aop
 */
class Aop
{
    /**
     * @var object
     */
    private $instance;
    /**
     * @var array
     */
    private $attributes = [];
    /**
     * @var array
     */
    private $onBeforeFunc = [];
    /**
     * @var array
     */
    private $onAfterFunc = [];
    /**
     * @var array
     */
    private $params = [];

    /**
     * Aop constructor.
     * @param object $instance
     * @param bool $isClone
     */
    public function __construct($instance, $isClone = false)
    {
        $isClone && ($instance = clone $instance);
        //$instance->aop = $this;
        $this->instance = $instance;
    }

    /**
     * @param $name
     * @return mixed|null
     */
    public function __get($name)
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    /**
     * @param $method
     * @param $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        $this->params['method'] = $method;
        $this->params['args'] = $args;
        unset($this->params['result']);

        foreach ($this->onBeforeFunc as $func) {
            $this->params = call_user_func_array($func, $this->params);
        }
        if (isset($this->params['result'])) {
            return $this->params['result'];
        }
        $this->params['result'] = call_user_func_array([$this->instance, $this->params['method']], $this->params['args']);
        foreach ($this->onAfterFunc as $func) {
            $this->params = call_user_func_array($func, $this->params);
        }

        return $this->params['result'];
    }

    /**
     * @param callable $callback
     */
    public function register($name, callable $callback)
    {
        if ($name == 'onBefore') {
            $this->onBeforeFunc[] = $callback;
        } elseif ($name == 'onAfter') {
            $this->onAfterFunc[] = $callback;
        } elseif ($name == 'onBoth') {
            $this->onBeforeFunc[] = $callback;
            $this->onAfterFunc[] = $callback;
        }
    }
}
