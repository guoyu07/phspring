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
     * @var
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
    private $data = [];

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
        $this->data['method'] = $method;
        $this->data['arguments'] = $args;
        unset($this->data['result']);

        foreach ($this->onBeforeFunc as $func) {
            $this->data = call_user_func_array($func, $this->data);
        }
        if (isset($this->data['result'])) {
            return $this->data['result'];
        }
        $this->data['result'] = call_user_func_array([$this->instance, $this->data['method']],
            $this->data['arguments']);
        foreach ($this->onAfterFunc as $func) {
            $this->data = call_user_func_array($func, $this->data);
        }

        return $this->data['result'];
    }

    /**
     * @param callable $callback
     */
    public function registerOnBefore(callable $callback)
    {
        $this->onBeforeFunc[] = $callback;
    }

    /**
     * @param callable $callback
     */
    public function registerOnAfter(callable $callback)
    {
        $this->onAfterFunc[] = $callback;
    }

    /**
     * @param callable $callback
     */
    public function registerOnBoth(callable $callback)
    {
        $this->onBeforeFunc[] = $callback;
        $this->onAfterFunc[] = $callback;
    }
}
