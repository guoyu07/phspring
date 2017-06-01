<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\coroutine;

use phspring\context\Ac;
use phspring\context\CoroutineContext;
use phspring\core\IRecoverable;
use phspring\core\PoolBean;
use phspring\exception\CoroutineException;

/**
 * Class Task
 * @package phspring\coroutine
 */
class Task extends PoolBean
{
    /**
     * @var \Generator
     */
    public $routine;
    /**
     * @var CoroutineContext
     */
    public $coroutineContext;
    /**
     * @var bool
     */
    public $clear = false;
    /**
     * @var array
     */
    public $asyncCallbacks = [];
    /**
     * @var \SplStack
     */
    protected $stack;

    /**
     * Task init.
     * @param \Generator $routine
     * @param CoroutineContext $coroutineContext
     */
    public function init(\Generator $routine, CoroutineContext $coroutineContext)
    {
        $this->routine = $routine;
        $this->coroutineContext = $coroutineContext;
        $this->stack = new \SplStack();
    }

    /**
     * coroutine schedule
     */
    public function run()
    {
        $routine = &$this->routine;
        try {
            if (!$routine) {
                return;
            }
            $value = $routine->current();
            // Nested coroutine
            if ($value instanceof \Generator) {
                $this->coroutineContext->addYieldStack($value->key());
                $this->stack->push($routine);
                $routine = $value;
                return;
            }

            if ($value != null && $value instanceof IBase) {
                if ($value->isTimeout()) { // timeout
                    try {
                        $value->throwSwooleException();
                    } catch (\Exception $e) {
                        $this->handleTaskTimeout($e, $value);
                    }
                    unset($value);
                    $routine->send(null);
                } else { // normal
                    $result = $value->getResult();
                    if ($result !== Instance::get()) {
                        unset($value);
                        $routine->send($result);
                    }
                }

                while (!$routine->valid() && !$this->stack->isEmpty()) {
                    $result = $routine->getReturn();
                    $this->routine = $this->stack->pop();
                    $this->routine->send($result);
                    $this->coroutineContext->popYieldStack();
                }
            } else {
                if ($routine->valid()) {
                    $routine->send($value);
                } else {
                    if (count($this->stack) > 0) {
                        $result = $routine->getReturn();
                        $this->routine = $this->stack->pop();
                        $this->routine->send($result);
                    }
                }
            }
        } catch (\Exception $e) {
            if (empty($value)) {
                $value = '';
            }

            $runTaskException = $this->handleTaskException($e, $value);
            if ($this->coroutineContext->getController() != null) {
                call_user_func([$this->coroutineContext->getController(), 'onExceptionHandle'], $runTaskException);
            } else {
                $routine->throw($runTaskException);
            }
            unset($value);
        }
    }

    /**
     * @param \Exception $e
     * @param $value
     * @return CoroutineException
     */
    public function handleTaskTimeout(\Exception $e, $value)
    {
        if ($value != '') {
            $log = '';
            dumpCoroutineTaskMessage($log, $value, 0);
            $message = 'Yield ' . $log . ' message: ' . $e->getMessage();
        } else {
            $message = 'Message: ' . $e->getMessage();
        }

        $runTaskException = new CoroutineException($message, $e->getCode(), $e);
        $this->coroutineContext->getTraceStack($this->routine->key());
        $this->coroutineContext->setErrorFile($e->getFile(), $e->getLine());
        $this->coroutineContext->setErrorMessage($message);

        if ($runTaskException instanceof SwooleException) {
            $runTaskException->setShowOther($this->coroutineContext->getTraceStack() . "\n" . $e->getTraceAsString(),
                $this->coroutineContext->getController());
        }
        $this->coroutineContext->getController()->log->warning($message);

        if (!empty($value) && $value instanceof IBase && method_exists($value, 'cleanup')) {
            $value->cleanup();
        }

        return $runTaskException;
    }

    /**
     * @param \Exception $e
     * @param $value
     * @return CoroutineException
     */
    public function handleTaskException(\Exception $e, $value)
    {
        if ($value != '') {
            $logValue = '';
            dumpCoroutineTaskMessage($logValue, $value, 0);
            $message = 'Yield ' . $logValue . ' message: ' . $e->getMessage();
        } else {
            $message = 'message: ' . $e->getMessage();
        }

        $runTaskException = new CoroutineException($message, $e->getCode(), $e);
        $this->coroutineContext->setErrorFile($e->getFile(), $e->getLine());
        $this->coroutineContext->setErrorMessage($message);

        while (!$this->stack->isEmpty()) {
            $this->routine = $this->stack->pop();
            try {
                $this->routine->throw($runTaskException);
                break;
            } catch (\Exception $e) {

            }
        }

        if ($runTaskException instanceof SwooleException) {
            $runTaskException->setShowOther($this->coroutineContext->getTraceStack() . "\n" . $e->getTraceAsString(),
                $this->coroutineContext->getController());
        }

        if (!empty($value) && $value instanceof IBase && method_exists($value, 'cleanup')) {
            $value->cleanup();
        }

        return $runTaskException;
    }

    /**
     * cleanup
     */
    public function cleanup()
    {
        if (!$this->clear) {
            unset(Ac::$appContext->scheduler->taskMap[$this->coroutineContext->uuid]);
            unset(Ac::$appContext->scheduler->ioCallbacks[$this->coroutineContext->uuid]);
            $this->coroutineContext->cleanup();
            $this->coroutineContext = null;
            $this->stack = null;
            $this->routine = null;
            $this->clear = true;
            Ac::getBean('pool')->push($this);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check task is finished.
     * @return boolean [description]
     */
    public function isFinished()
    {
        return !empty($this->stack) && $this->stack->isEmpty() && !$this->routine->valid();
    }

    /**
     * @return \Generator
     */
    public function getRoutine()
    {
        return $this->routine;
    }
}