<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server\timer;

use phspring\net\server\event\IEvent;

/**
 * Class Timer
 * @package phspring\net\server\timer
 */
class Timer
{
    /**
     * Tasks that based on ALARM signal.
     * [
     *   runTime => [[$func, $args, $persistent, timeInterval],[$func, $args, $persistent, timeInterval],..]],
     *   runTime => [[$func, $args, $persistent, timeInterval],[$func, $args, $persistent, timeInterval],..]],
     *   ...
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
     * @param int $timeInterval
     * @param callback $func
     * @param mixed $args
     * @param bool $persistent
     * @return int/false
     */
    public static function add($timeInterval, callable $func, $args = [], $persistent = true)
    {
        if ($timeInterval <= 0) {
            echo new \Exception('Bad timeInterval');
            return false;
        }

        if (self::$_event) {
            return self::$_event->add($timeInterval, $persistent ? IEvent::EV_TIMER : IEvent::EV_TIMER_ONCE, $func,
                $args);
        }

        if (empty(self::$_tasks)) {
            pcntl_alarm(1);
        }

        $runtime = time() + $timeInterval;
        if (!isset(self::$_tasks[$runtime])) {
            self::$_tasks[$runtime] = [];
        }
        self::$_tasks[$runtime][] = [$func, (array)$args, $persistent, $timeInterval];

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
