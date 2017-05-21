<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\context;

use phspring\mvc\Controller;

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
     * @var string
     */
    protected $controllerName;
    /**
     * @var string
     */
    protected $methodName;
    /**
     * @var array
     */
    protected $yieldStack;
    /**
     * @var int
     */
    protected $yieldLayer = 0;

    /**
     * CoroutineContext constructor.
     */
    public function __construct()
    {
        $this->yieldStack = [];
    }

    /**
     * @param $number
     */
    public function addYieldStack($number)
    {
        $this->yieldLayer++;
        $this->yieldStack[$this->yieldLayer][] = "| #第 {$this->yieldLayer} 层嵌套出错在第 ++$number 个yield后";
    }

    /**
     * @return
     */
    public function popYieldStack()
    {
        array_pop($this->yieldStack);
    }

    /**
     * @param $number
     */
    public function addYieldStackMessage($number)
    {
        $this->yieldStack[$this->yieldLayer][] = "| #第 {$this->yieldLayer} 层嵌套出错在第 ++$number 个yield后";
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
        $this->yieldStack[$this->yieldLayer][] = "| # Target function-> $controllerName::$methodName";
    }

    /**
     * get yieldStack trace
     */
    public function getTraceStack()
    {
        $trace = "Coroutine error trace: \n";
        foreach ($this->yieldStack as $i => $v) {
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
        $this->yieldStack[$this->yieldLayer][] = "| # Error file: $file($line)";
    }

    /**
     * @param $message
     */
    public function setErrorMessage($message)
    {
        $this->yieldStack[$this->yieldLayer][] = "| # Error message: $message";
    }

    /**
     * scavenger
     */
    public function scavenger()
    {
        $this->controller = null;
        $this->yieldStack = null;
    }
}
