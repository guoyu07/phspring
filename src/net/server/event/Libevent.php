<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server\event;

use phspring\net\server\Util;

/**
 * Class Libevent
 * Depend on libevent extension. <http://php.net/manual/en/book.libevent.php>
 * @package phspring\net\server\event
 */
class Libevent implements IEvent
{
    /**
     * Event base.
     * @var resource
     */
    protected $eventBase = null;
    /**
     * All listeners for read/write event.
     * @var array
     */
    protected $allEvents = [];
    /**
     * Event listeners of signal.
     * @var array
     */
    protected $eventSignal = [];
    /**
     * All timer event listeners.
     * [func, args, event, flag, time_interval]
     * @var array
     */
    protected $eventTimer = [];

    /**
     * construct
     */
    public function __construct()
    {
        $this->eventBase = event_base_new();
    }

    /**
     * {@inheritdoc}
     */
    public function add($fd, $flag, $func, $args = [])
    {
        switch ($flag) {
            case self::EV_SIGNAL:
                $fdKey = (int)$fd;
                $realFlag = EV_SIGNAL | EV_PERSIST;
                $this->eventSignal[$fdKey] = event_new();
                if (!event_set($this->eventSignal[$fdKey], $fd, $realFlag, $func, null)) {
                    return false;
                }
                if (!event_base_set($this->eventSignal[$fdKey], $this->eventBase)) {
                    return false;
                }
                if (!event_add($this->eventSignal[$fdKey])) {
                    return false;
                }
                return true;
            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:
                $event = event_new();
                $timeId = (int)$event;
                if (!event_set($event, 0, EV_TIMEOUT, [$this, 'timerCallback'], $timeId)) {
                    return false;
                }
                if (!event_base_set($event, $this->eventBase)) {
                    return false;
                }
                $timeInterval = $fd * 1000000;
                if (!event_add($event, $timeInterval)) {
                    return false;
                }
                $this->eventTimer[$timeId] = [$func, (array)$args, $event, $flag, $timeInterval];
                return $timeId;
            default :
                $fdKey = (int)$fd;
                $realFlag = $flag === self::EV_READ ? EV_READ | EV_PERSIST : EV_WRITE | EV_PERSIST;
                $event = event_new();
                if (!event_set($event, $fd, $realFlag, $func, null)) {
                    return false;
                }
                if (!event_base_set($event, $this->eventBase)) {
                    return false;
                }
                if (!event_add($event)) {
                    return false;
                }
                $this->allEvents[$fdKey][$flag] = $event;
                return true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function del($fd, $flag)
    {
        switch ($flag) {
            case self::EV_READ:
            case self::EV_WRITE:
                $fdKey = (int)$fd;
                if (isset($this->allEvents[$fdKey][$flag])) {
                    event_del($this->allEvents[$fdKey][$flag]);
                    unset($this->allEvents[$fdKey][$flag]);
                }
                if (empty($this->allEvents[$fdKey])) {
                    unset($this->allEvents[$fdKey]);
                }
                break;
            case  self::EV_SIGNAL:
                $fdKey = (int)$fd;
                if (isset($this->eventSignal[$fdKey])) {
                    event_del($this->eventSignal[$fdKey]);
                    unset($this->eventSignal[$fdKey]);
                }
                break;
            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:
                // 这里 fd 为timerid 
                if (isset($this->eventTimer[$fd])) {
                    event_del($this->eventTimer[$fd][2]);
                    unset($this->eventTimer[$fd]);
                }
                break;
        }

        return true;
    }

    /**
     * Timer callback.
     *
     * @param mixed $null1
     * @param int $null2
     * @param mixed $timeId
     */
    protected function timerCallback($null1, $null2, $timeId)
    {
        if ($this->eventTimer[$timeId][3] === self::EV_TIMER) {
            event_add($this->eventTimer[$timeId][2], $this->eventTimer[$timeId][4]);
        }
        try {
            call_user_func_array($this->eventTimer[$timeId][0], $this->eventTimer[$timeId][1]);
        } catch (\Throwable $e) {
            Util::log($e) && exit(250);
        }
        if (isset($this->eventTimer[$timeId]) && $this->eventTimer[$timeId][3] === self::EV_TIMER_ONCE) {
            $this->del($timeId, self::EV_TIMER_ONCE);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearAllTimer()
    {
        foreach ($this->eventTimer as $task_data) {
            event_del($task_data[2]);
        }
        $this->eventTimer = [];
    }

    /**
     * {@inheritdoc}
     */
    public function loop()
    {
        event_base_loop($this->eventBase);
    }

    /**
     * Destroy loop.
     *
     * @return void
     */
    public function destroy()
    {
        foreach ($this->eventSignal as $event) {
            event_del($event);
        }
    }
}
