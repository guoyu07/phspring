<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\coroutine;

use phspring\context\Ac;

/**
 * Class Base
 * @package phspring\coroutine
 */
abstract class Base implements IBase
{
    /**
     * @var int
     */
    public static $maxTimeout = 0;
    /**
     * @var
     */
    public $result;
    /**
     * @var int <ms>
     */
    public $timeout;
    /**
     * @var float
     */
    public $requestTime = 0.0;
    /**
     * @var float
     */
    public $responseTime = 0.0;
    /**
     * @var \phspring\coroutine\Task
     */
    public $coroutine;
    /**
     * io back or not
     * @var bool
     */
    public $ioBack = false;

    /**
     * Base constructor.
     * @param int $timeout
     */
    public function __construct($timeout = 0)
    {
        if (self::$maxTimeout == 0) {
            self::$maxTimeout = Ac::config()->get('coroutine.timeout', 5000);
        }
        $this->timeout = $timeout > 0 ? $timeout : self::$maxTimeout;
        $this->result = Instance::get();
        $this->requestTime = microtime(true);
    }

    /**
     * @param callable $callback
     * @return mixed
     */
    public abstract function send(callable $callback);

    /**
     * @return null
     */
    public function getResult()
    {
        if ($this->isTimeout() && !$this->ioBack) {
            return null;
        }

        return $this->result;
    }

    /**
     * @return bool
     */
    public function isTimeout()
    {
        if (!$this->ioBack && (1000 * (microtime(true) - $this->requestTime) > $this->timeout)) {
            return true;
        }

        return false;
    }

    /**
     * Continue run
     * @param string $uuid Request unique id
     * @return bool
     */
    public function continueRun($uuid)
    {
        if (empty(Ac::$appContext->scheduler->ioCallback[$uuid])) {
            return true;
        }
        /* @var $coroutine Base */
        foreach (Ac::$appContext->scheduler->ioCallback[$uuid] as $idx => $coroutine) {
            if ($coroutine->ioBack && !empty(Ac::$appContext->scheduler->taskMap[$uuid])) {
                unset(Ac::$appContext->scheduler->ioCallback[$uuid][$idx]);
                Ac::$appContext->scheduler->schedule(Ac::$appContext->scheduler->taskMap[$uuid]);
            } else {
                break;
            }
        }

        return true;
    }
}