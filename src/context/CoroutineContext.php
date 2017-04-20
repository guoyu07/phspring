<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\context;

/**
 * Class CoroutineContext
 * @package phspring\context
 */
class CoroutineContext extends Context
{
    /**
     * @var Controller
     */
    protected $controller;
    /**
     * @var
     */
    protected $controllerName;
    /**
     * @var
     */
    protected $methodName;
    /**
     * @var array
     */
    protected $stack;
    /**
     * @var int
     */
    protected $layer = 0;

    /**
     * CoroutineContext constructor.
     */
    public function __construct()
    {
        $this->stack = [];
    }

    /**
     * @param $number
     */
    public function addYieldStack($number)
    {
        $this->layer++;
        $this->stack[$this->layer][] = "| #第 {$this->layer} 层嵌套出错在第 ++$number 个yield后";
    }

    /**
     * @return
     */
    public function popYieldStack()
    {
        array_pop($this->stack);
    }

    /**
     * @param $number
     */
    public function setStackMessage($number)
    {
        $this->stack[$this->layer][] = "| #第 {$this->layer} 层嵌套出错在第 ++$number 个yield后";
    }

    /**
     * @return
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * @param string $controller
     * @param string $controllerName
     * @param string $methodName
     */
    public function setController($controller, $controllerName, $methodName)
    {
        $this->controller = $controller;
        $this->controllerName = $controllerName;
        $this->methodName = $methodName;
        $this->stack[$this->layer][] = "| #目标函数： $controllerName -> $methodName";
    }

    /**
     * get stack trace
     */
    public function getTraceStack()
    {
        $trace = "协程错误指南: \n";
        foreach ($this->stack as $i => $v) {
            foreach ($v as $value) {
                $trace .= "{$value}\n";
            }
        }
        $trace = trim($trace);

        return $trace;
    }

    /**
     * @param $file
     * @param $line
     */
    public function setErrorFile($file, $line)
    {
        $this->stack[$this->layer][] = "| #出错文件: $file($line)";
    }

    /**
     * @param $message
     */
    public function setErrorMessage($message)
    {
        $this->stack[$this->layer][] = "| #报错消息: $message";
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        unset($this->controller);
        unset($this->stack);
    }
}
