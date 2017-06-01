<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server\event;

use phspring\net\server\Util;

/**
 * Class Event
 * Depend on Event extension. <http://php.net/manual/en/book.event.php>
 * @package phspring\net\server\event
 */
class Event implements IEvent
{
    /**
     * Event base.
     * @var object
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
     * Timer id.
     * @var int
     */
    protected static $timerId = 1;

    /**
     * construct
     * @return void
     */
    public function __construct()
    {
        $this->eventBase = new \EventBase();
    }

    /**
     * {@inheritdoc}
     * @see IEvent::add()
     *
     * @param mixed $fd
     * @param int $flag
     * @param callable $func
     * @param array $args
     * @return bool|int
     */
    public function add($fd, $flag, $func, $args = [])
    {
        switch ($flag) {
            case self::EV_SIGNAL:
                $fdKey = (int)$fd;
                $event = \Event::signal($this->eventBase, $fd, $func);
                if (!$event || !$event->add()) {
                    return false;
                }
                $this->eventSignal[$fdKey] = $event;
                return true;
            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:
            $param = [$func, (array)$args, $flag, $fd, self::$timerId];
            $event = new \Event($this->eventBase, -1, \Event::TIMEOUT | \Event::PERSIST,
                [$this, 'timerCallback'], $param);
            if (!$event || !$event->addTimer($fd)) {
                    return false;
                }
                $this->eventTimer[self::$timerId] = $event;
                return self::$timerId++;
            default :
                $fdKey = (int)$fd;
                $realFlag = $flag === self::EV_READ ? \Event::READ | \Event::PERSIST : \Event::WRITE | \Event::PERSIST;
                $event = new \Event($this->eventBase, $fd, $realFlag, $func, $fd);
                if (!$event || !$event->add()) {
                    return false;
                }
                $this->allEvents[$fdKey][$flag] = $event;
                return true;
        }
    }

    /**
     * @param mixed $fd
     * @param int $flag
     * @return bool
     */
    public function del($fd, $flag)
    {
        switch ($flag) {
            case self::EV_READ:
            case self::EV_WRITE:
                $fdKey = (int)$fd;
                if (isset($this->allEvents[$fdKey][$flag])) {
                    $this->allEvents[$fdKey][$flag]->del();
                    unset($this->allEvents[$fdKey][$flag]);
                }
                if (empty($this->allEvents[$fdKey])) {
                    unset($this->allEvents[$fdKey]);
                }
                break;
            case  self::EV_SIGNAL:
                $fdKey = (int)$fd;
                if (isset($this->eventSignal[$fdKey])) {
                    $this->eventSignal[$fdKey]->del();
                    unset($this->eventSignal[$fdKey]);
                }
                break;
            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:
                if (isset($this->eventTimer[$fd])) {
                    $this->eventTimer[$fd]->del();
                    unset($this->eventTimer[$fd]);
                }
                break;
        }

        return true;
    }

    /**
     * Timer callback.
     * @param null $fd
     * @param int $what
     * @param int $timerId
     */
    public function timerCallback($fd, $what, $param)
    {
        $timerId = $param[4];

        if ($param[2] === self::EV_TIMER_ONCE) {
            $this->eventTimer[$timerId]->del();
            unset($this->eventTimer[$timerId]);
        }

        try {
            call_user_func_array($param[0], $param[1]);
        } catch (\Throwable $e) {
            Util::log($e) && exit(250);
        }
    }

    /**
     * @return void
     */
    public function clearAllTimer()
    {
        foreach ($this->eventTimer as $event) {
            $event->del();
        }
        $this->eventTimer = [];
    }


    /**
     * @return void
     */
    public function loop()
    {
        $this->eventBase->loop();
    }

    /**
     * Destroy loop.
     * @return void
     */
    public function destroy()
    {
        foreach ($this->eventSignal as $event) {
            $event->del();
        }
    }
}
