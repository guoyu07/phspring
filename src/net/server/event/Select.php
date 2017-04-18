<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server\event;

/**
 * Class Select
 * Depend on pcntl extension. <http://php.net/manual/en/book.pcntl.php>
 * @package phspring\net\server\event
 */
class Select implements IEvent
{
    /**
     * All listeners for read/write event.
     * @var array
     */
    public $allEvents = [];
    /**
     * Event listeners of signal.
     * @var array
     */
    public $signalEvents = [];
    /**
     * Fds waiting for read event.
     * @var array
     */
    protected $readFds = [];
    /**
     * Fds waiting for write event.
     * @var array
     */
    protected $writeFds = [];
    /**
     * Timer scheduler.
     * {['data':timer_id, 'priority':run_timestamp], ..}
     * @var \SplPriorityQueue
     */
    protected $scheduler = null;
    /**
     * All timer event listeners.
     * [[func, args, flag, timer_interval], ..]
     * @var array
     */
    protected $task = [];
    /**
     * Timer id.
     * @var int
     */
    protected $timerId = 1;
    /**
     * Select timeout.
     * @var int
     */
    protected $selectTimeout = 100000000;
    /**
     * Paired socket channels
     * @var array
     */
    protected $channel = [];

    /**
     * Construct.
     */
    public function __construct()
    {
        // Create a pipeline and put into the collection of the read to read the descriptor to avoid empty polling.
        $this->channel = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($this->channel) {
            stream_set_blocking($this->channel[0], 0);
            $this->readFds[0] = $this->channel[0];
        }
        // Init SplPriorityQueue.
        $this->scheduler = new \SplPriorityQueue();
        $this->scheduler->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
    }

    /**
     * {@inheritdoc}
     */
    public function add($fd, $flag, $func, $args = [])
    {
        switch ($flag) {
            case self::EV_READ:
                $fdKey = (int)$fd;
                $this->allEvents[$fdKey][$flag] = [$func, $fd];
                $this->readFds[$fdKey] = $fd;
                break;
            case self::EV_WRITE:
                $fdKey = (int)$fd;
                $this->allEvents[$fdKey][$flag] = [$func, $fd];
                $this->writeFds[$fdKey] = $fd;
                break;
            case self::EV_SIGNAL:
                $fdKey = (int)$fd;
                $this->signalEvents[$fdKey][$flag] = [$func, $fd];
                pcntl_signal($fd, [$this, 'signalHandler']);
                break;
            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:
                $runTime = microtime(true) + $fd;
                $this->scheduler->insert($this->timerId, -$runTime);
                $this->task[$this->timerId] = [$func, (array)$args, $flag, $fd];
                $this->tick();
                return $this->timerId++;
        }

        return true;
    }

    /**
     * Signal handler.
     *
     * @param int $signal
     */
    public function signalHandler($signal)
    {
        call_user_func_array($this->signalEvents[$signal][self::EV_SIGNAL][0], [$signal]);
    }

    /**
     * {@inheritdoc}
     */
    public function del($fd, $flag)
    {
        $fdKey = (int)$fd;
        switch ($flag) {
            case self::EV_READ:
                unset($this->allEvents[$fdKey][$flag], $this->readFds[$fdKey]);
                if (empty($this->allEvents[$fdKey])) {
                    unset($this->allEvents[$fdKey]);
                }
                return true;
            case self::EV_WRITE:
                unset($this->allEvents[$fdKey][$flag], $this->writeFds[$fdKey]);
                if (empty($this->allEvents[$fdKey])) {
                    unset($this->allEvents[$fdKey]);
                }
                return true;
            case self::EV_SIGNAL:
                unset($this->signalEvents[$fdKey]);
                pcntl_signal($fd, SIG_IGN);
                break;
            case self::EV_TIMER:
            case self::EV_TIMER_ONCE;
                unset($this->task[$fdKey]);
                return true;
        }
        return false;
    }

    /**
     * Tick for timer.
     *
     * @return void
     */
    protected function tick()
    {
        while (!$this->scheduler->isEmpty()) {
            $schedulerData = $this->scheduler->top();
            $timeId = $schedulerData['data'];
            $nextRunTime = -$schedulerData['priority'];
            $timeNow = microtime(true);
            $this->selectTimeout = ($nextRunTime - $timeNow) * 1000000;
            if ($this->selectTimeout <= 0) {
                $this->scheduler->extract();

                if (!isset($this->task[$timeId])) {
                    continue;
                }

                // [func, args, flag, timer_interval]
                $taskData = $this->task[$timeId];
                if ($taskData[2] === self::EV_TIMER) {
                    $nextRunTime = $timeNow + $taskData[3];
                    $this->scheduler->insert($timeId, -$nextRunTime);
                }
                call_user_func_array($taskData[0], $taskData[1]);
                if (isset($this->task[$timeId]) && $taskData[2] === self::EV_TIMER_ONCE) {
                    $this->del($timeId, self::EV_TIMER_ONCE);
                }
                continue;
            }
            return;
        }
        $this->selectTimeout = 100000000;
    }

    /**
     * {@inheritdoc}
     */
    public function clearAllTimer()
    {
        $this->scheduler = new \SplPriorityQueue();
        $this->scheduler->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
        $this->task = [];
    }

    /**
     * {@inheritdoc}
     */
    public function loop()
    {
        $e = null;
        while (1) {
            // Calls signal handlers for pending signals
            pcntl_signal_dispatch();

            $read = $this->readFds;
            $write = $this->writeFds;
            // Waiting read/write/signal/timeout events.
            $res = @stream_select($read, $write, $e, 0, $this->selectTimeout);
            if (!$this->scheduler->isEmpty()) {
                $this->tick();
            }
            if (!$res) {
                continue;
            }
            foreach ($read as $fd) {
                $fdKey = (int)$fd;
                if (isset($this->allEvents[$fdKey][self::EV_READ])) {
                    call_user_func_array($this->allEvents[$fdKey][self::EV_READ][0],
                        [$this->allEvents[$fdKey][self::EV_READ][1]]);
                }
            }
            foreach ($write as $fd) {
                $fdKey = (int)$fd;
                if (isset($this->allEvents[$fdKey][self::EV_WRITE])) {
                    call_user_func_array($this->allEvents[$fdKey][self::EV_WRITE][0],
                        [$this->allEvents[$fdKey][self::EV_WRITE][1]]);
                }
            }
        }
    }

    /**
     * Destroy loop.
     *
     * @return void
     */
    public function destroy()
    {

    }
}
