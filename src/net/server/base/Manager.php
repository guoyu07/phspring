<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server\base;

use phspring\context\Ac;
use phspring\net\server\Timer;
use phspring\toolbox\helper\ProcessHelper;

/**
 * Class Manager
 * @package phspring\net\server\base
 */
abstract class Manager
{
    /**
     * @var array
     */
    public static $defaultTransports = [
        'tcp' => 'tcp',
        'udp' => 'udp',
        'unix' => 'unix',
        'ssl' => 'tcp'
    ];
    /**
     * @var callback
     */
    public static $onManagerReload = null;
    /**
     * @var callback
     */
    public static $onManagerStop = null;

    /**
     * Daemonize.
     * @var bool
     */
    protected static $daemonize = false;
    /**
     * Stdout file.
     * @var string
     */
    protected static $stdoutFile = '/dev/null';
    /**
     * @var int
     */
    protected static $pid = 0;
    /**
     * @var string
     */
    protected static $pidPath = '';
    /**
     * @var event\IEvent
     */
    protected static $globalEvent = null;
    /**
     * @var string
     */
    protected static $eventName = '';
    /**
     * @var array <Worker, Worker, ...>
     */
    protected static $workers = [];
    /**
     * The format is like this
     * [
     *     workerId => [
     *         pid1 => pid1,
     *         pid2 => pid2,
     *     ],
     * ]
     * @var array
     */
    //public static $workerPidMap = [];
    /**
     * The format is like this
     * [
     *     workerId => [
     *         pid1,
     *         pid2,
     *     ],
     * ]
     * @var array
     */
    protected static $workersPids = [];

    /**
     * The format is like this
     * [
     *     pid1 => pid1,
     *     pid2 => pid2
     * ].
     * @var array
     */
    protected static $pidsForReload = [];
    /**
     * @var int
     */
    protected static $status = Macro::STATUS_STARTING;

    /**
     * @var array
     */
    protected static $_statsData = [];

    /**
     * Run all worker instances.
     * @return void
     */
    public static function run()
    {
        static::prepare();
        static::daemonize();
        static::initWorkers();
        static::installSignal();
        static::forkWorkers();
        static::displayUI();
        static::resetStd();
        static::monitorWorkers();
    }

    /**
     * set manager pid
     * @param $pid
     */
    public static function setpid()
    {
        self::$pid = posix_getpid();
    }

    /**
     * get manager pid
     * @return int
     */
    public static function getPid()
    {
        return self::$pid;
    }

    /**
     * set global event
     * @param $globalEvent
     */
    public static function setGlobalEvent($globalEvent)
    {
        self::$globalEvent = $globalEvent;
    }

    /**
     * Get the event loop instance.
     * @return IEvent
     */
    public static function getGlobalEvent()
    {
        return self::$globalEvent;
    }

    /**
     * @return string
     */
    public static function getEventName()
    {
        return self::$eventName;
    }

    /**
     * set worker
     * @param $workId
     * @param Worker $worker
     */
    public static function setWorker($workId, Worker $worker)
    {
        self::$workers[$workId] = $worker;
    }

    /**
     * set workPids
     * @param $workId
     * @param $val
     */
    public static function setWorkPids($workId, $val)
    {
        self::$workersPids[$workId] = $val;
    }

    /**
     * @param int $signal
     */
    public static function signalHandler($signal)
    {
        switch ($signal) {
            // Stop.
            case SIGINT:
                self::stopAll();
                break;
            // Reload.
            case SIGUSR1:
                self::$pidsForReload = self::getAllWorkerPids();
                self::reload();
                break;
            // Show status.
            case SIGUSR2:
                Util::writeStatisticsToStatusFile();
                break;
        }
    }

    /**
     * @return array
     */
    public static function getAllWorkers()
    {
        return self::$workers;
    }

    /**
     * @return array
     * Data structure like this.
     * [
     *     pid1 => pid1,
     *     pid2 => pid2,
     * ]
     */
    public static function getAllWorkerPids()
    {
        $all = [];

        foreach (self::$workersPids as $pids) {
            foreach ($pids as $pid) {
                if ($pid == 0) {
                    continue;
                }
                $all[$pid] = $pid;
            }
        }

        return $all;
    }

    /**
     * @param $worker Worker
     */
    public static function getWorkerPids(Worker $worker)
    {
        return array_values(array_filter(self::$workersPids[$worker->workerId]));
    }

    /**
     * Stop.
     * @return void
     */
    public static function stopAll()
    {
        self::$status = Macro::STATUS_SHUTDOWN;
        // For manager process.
        if (self::$pid === posix_getpid()) {
            Util::log('phspring[' . basename(self::$startFile) . '] stopping ...');
            $pids = self::getAllWorkerPids();
            foreach ($pids as $pid) {
                posix_kill($pid, SIGINT);
                Timer::add(Macro::KILL_WORKER_TIMER_TIME, 'posix_kill', [$pid, SIGKILL], false);
            }
        } else { // For child processes.
            foreach (self::$workers as $worker) {
                $worker->stop();
            }
            self::$globalEvent->destroy();
            exit(0);
        }
    }

    /**
     * Install signal handler.
     *
     * @return void
     */
    public static function installSignal()
    {
        // stop
        pcntl_signal(SIGINT, [self, 'signalHandler'], false);
        // reload
        pcntl_signal(SIGUSR1, [self, 'signalHandler'], false);
        // status
        pcntl_signal(SIGUSR2, [self, 'signalHandler'], false);
        // ignore
        pcntl_signal(SIGPIPE, SIG_IGN, false);
    }

    /**
     * Reinstall signal handler.
     *
     * @return void
     */
    public static function reinstallSignal($event)
    {
        // uninstall stop signal handler
        pcntl_signal(SIGINT, SIG_IGN, false);
        // uninstall reload signal handler
        pcntl_signal(SIGUSR1, SIG_IGN, false);
        // uninstall  status signal handler
        pcntl_signal(SIGUSR2, SIG_IGN, false);
        // reinstall stop signal handler
        $event->add(SIGINT, IEvent::EV_SIGNAL, [self, 'signalHandler']);
        // reinstall  reload signal handler
        $event->add(SIGUSR1, IEvent::EV_SIGNAL, [self, 'signalHandler']);
        // reinstall  status signal handler
        $event->add(SIGUSR2, IEvent::EV_SIGNAL, [self, 'signalHandler']);
    }

    /**
     * prepare.
     * @return void
     */
    protected static function prepare()
    {
        // Pid file.
        self::$pidPath = self::getPidSavePath();
        // Util title.
        ProcessHelper::setProcessTitle(Ac::config()->get('server.processTitle'));
        // Stats.
        Util::setStatsData('startTime', time());
        // Init data for worker id.
        self::initWorkerPids();
        // Timer init.
        Timer::init();
        // select a event
        self::$eventName = Util::choiceEventLoop(self::$eventLoop);
        // set status
        self::$status = Macro::STATUS_STARTING;
        static::parseCommand();
    }

    /**
     * get manager pid save path
     * @return string
     */
    protected static function getPidSavePath()
    {
        $dir = Ac::config()->get('server.pidSaveDir');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $backtrace = debug_backtrace();
        $fileName = str_replace('/', '_', $backtrace[count($backtrace) - 1]['file']);

        return $dir . $fileName . '.pid';
    }

    /**
     * manager daemonize
     */
    protected static function daemonize()
    {
        if (self::$daemonize) {
            ProcessHelper::daemonize();
        }
        self::setPid();
        self::savePid();
    }

    /**
     * save manager pid to file
     */
    protected static function savePid()
    {
        ProcessHelper::savePid(1, self::$pidPath);
    }

    /**
     * reset std
     */
    protected static function resetStd()
    {
        if (self::$daemonize) {
            ProcessHelper::resetStd(self::$stdoutFile);
        }
    }

    /**
     * @return void
     */
    protected static function initWorkers()
    {
        /* @var $worker Worker */
        foreach (self::$workers as $worker) {
            if (empty($worker->name)) {
                $worker->name = 'nobody';
            }
            if (empty($worker->user)) {
                $worker->user = ProcessHelper::getUserName();
            } else {
                if (posix_getuid() !== 0 && $worker->user != ProcessHelper::getUserName()) {
                    echo 'Warning: You must have the root privileges to change the uid or gid.';
                }
            }
            if (!$worker->reusePort) {
                $worker->listen();
            }
        }
    }

    /**
     * Init idMap.
     * return void
     */
    protected static function initWorkerPids()
    {
        /* @var $worker Worker */
        foreach (self::$workers as $workerId => $worker) {
            $ids = [];
            for ($i = 0; $i < $worker->count; $i++) {
                $ids[$i] = self::$workersPids[$workerId][$i] ?? 0;
            }
            self::$workersPids[$workerId] = $ids;
        }
    }

    /**
     * @return void
     */
    protected static function forkWorkers()
    {
        /* @var $worker Worker */
        foreach (self::$workers as $worker) {
            if (self::$status === Macro::STATUS_STARTING) {
                if (empty($worker->name)) {
                    $worker->name = $worker->getSocketName();
                }
            }

            //while (count(self::$workerPidMap[$worker->workerId]) < $worker->count) {
            while (count(self::getWorkerPids($worker)) < $worker->count) {
                static::forkWorker($worker);
            }
        }
    }

    /**
     * parse command
     */
    abstract protected static function parseCommand();

    /**
     * fork one worker
     * @param Worker $worker
     * @throws Exception
     */
    abstract protected static function forkWorker($worker);

    /**
     * @return void
     */
    abstract protected static function exitAndDestoryAll();

    /**
     * @return void
     */
    abstract protected static function reload();

    /**
     * @return void
     */
    abstract protected static function monitorWorkers();

    /**
     * display ui
     */
    abstract protected static function displayUI();
}
