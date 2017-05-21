<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\coroutine;

use phspring\context\CoroutineContext;
use phspring\core\Bean;

/**
 * Class Scheduler
 * @package phspring\coroutine
 */
class Scheduler extends Bean
{
    /**
     * @var array
     */
    public $ioCallbacks;
    /**
     * @var \SplQueue
     */
    public $taskQueue;
    /**
     * @var array
     */
    public $taskMap = [];

    /**
     * Coroutine scheduler constructor.
     */
    public function __construct()
    {
        $this->taskQueue = new \SplQueue();

        swoole_timer_tick(1, function ($timerId) {
            $this->run();
        });

        swoole_timer_tick(1000, function ($timerId) {
            if (empty($this->ioCallbacks)) {
                return true;
            }

            foreach ($this->ioCallbacks as $uuid => $callbacks) {
                /* @var $callBack Base */
                foreach ($callbacks as $callBack) {
                    if ($callBack->ioBack) {
                        continue;
                    }
                    if ($callBack->isTimeout()) {
                        $this->schedule($this->taskMap[$uuid]);
                    }
                }
            }
        });
    }

    /**
     * run scheduler
     */
    public function run()
    {
        while (!$this->taskQueue->isEmpty()) {
            /* @var $task Task */
            $task = $this->taskQueue->dequeue();
            $task->run();
            if (empty($task->routine)) {
                continue;
            }
            if ($task->routine->valid() && ($task->routine->current() instanceof IBase)) {
                //...
            } else {
                if ($task->isFinished()) {
                    $task->destroy();
                } else {
                    $this->schedule($task);
                }
            }
        }
    }

    /**
     * @param CoroutineTask $task
     * @return $this
     */
    public function schedule(Task $task)
    {
        $this->taskQueue->enqueue($task);
        return $this;
    }

    /**
     * @param \Generator $routine
     * @param CoroutineContext $coroutineContext
     */
    public function start(\Generator $routine, CoroutineContext $coroutineContext)
    {
        $task = new Task($routine, $coroutineContext);
        $this->ioCallbacks[$coroutineContext->uuid] = [];
        $this->taskMap[$coroutineContext->uuid] = $task;
        $this->taskQueue->enqueue($task);
    }
}
