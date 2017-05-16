<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server;

use phspring\net\server\event\IEvent;

/**
 * Class Timer
 * @package phspring\net\server
 */
class Timer
{
    /**
     * Tasks that based on ALARM signal.
     * [
     *   run_time => [[$func, $args, $persistent, time_interval],[$func, $args, $persistent, time_interval],..]],
     *   run_time => [[$func, $args, $persistent, time_interval],[$func, $args, $persistent, time_interval],..]],
     *   ..
     * ]
     * @var array
     */
    private static $_tasks = [];

    /**
     * event
     * @var IEvent
     */
    private static $_event = null;

    /**
     * Init.
     * @param IEvent $event
     * @return void
     */
    public static function init($event = null)
    {
        if ($event) {
            self::$_event = $event;
        } else {
            pcntl_signal(SIGALRM, [Timer::class, 'signalHandle'], false);
        }
    }

    /**
     * ALARM signal handler.
     * @return void
     */
    public static function signalHandle()
    {
        if (!self::$_event) {
            pcntl_alarm(1);
            self::tick();
        }
    }

    /**
     * Add a timer.
     *
     * @param int $time_interval
     * @param callback $func
     * @param mixed $args
     * @param bool $persistent
     * @return int/false
     */
    public static function add($timeInterval, $func, $args = [], $persistent = true)
    {
        if ($timeInterval <= 0) {
            echo new \Exception("bad time_interval");
            return false;
        }

        if (self::$_event) {
            return self::$_event->add($timeInterval,
                $persistent ? IEvent::EV_TIMER : IEvent::EV_TIMER_ONCE, $func, $args);
        }

        if (!is_callable($func)) {
            echo new \Exception('not callable');
            return false;
        }

        if (empty(self::$_tasks)) {
            pcntl_alarm(1);
        }

        $now = time();
        $runTime = $now + $timeInterval;
        if (!isset(self::$_tasks[$runTime])) {
            self::$_tasks[$runTime] = [];
        }
        self::$_tasks[$runTime][] = [$func, (array)$args, $persistent, $timeInterval];

        return 1;
    }


    /**
     * Tick.
     *
     * @return void
     */
    public static function tick()
    {
        if (empty(self::$_tasks)) {
            pcntl_alarm(0);
            return;
        }

        $now = time();
        foreach (self::$_tasks as $runTime => $data) {
            if ($now >= $runTime) {
                foreach ($data as $idx => $task) {
                    $func = $task[0];
                    $args = $task[1];
                    $persistent = $task[2];
                    $timeInterval = $task[3];
                    try {
                        call_user_func_array($func, $args);
                    } catch (\Exception $e) {
                        echo $e;
                    }
                    if ($persistent) {
                        self::add($timeInterval, $func, $args);
                    }
                }
                unset(self::$_tasks[$runTime]);
            }
        }
    }

    /**
     * Remove a timer.
     *
     * @param mixed $timerId
     * @return bool
     */
    public static function del($timerId)
    {
        if (self::$_event) {
            return self::$_event->del($timerId, IEvent::EV_TIMER);
        }

        return false;
    }

    /**
     * Remove all timers.
     *
     * @return void
     */
    public static function delAll()
    {
        self::$_tasks = [];
        pcntl_alarm(0);
        if (self::$_event) {
            self::$_event->clearAllTimer();
        }
    }
}
