<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server;

use phspring\context\Ac;

/**
 * Class Manager
 * @package phspring\net\server
 */
class Manager
{
    /**
     * Daemonize.
     * @var bool
     */
    public static $daemonize = false;
    /**
     * Stdout file.
     * @var string
     */
    public static $stdoutFile = '/dev/null';
    /**
     * @var int
     */
    protected static $managerPid = 0;
    /**
     * @var string
     */
    public static $managerPidPath = '';
    /**
     * @var event\IEvent
     */
    public static $event = null;
    /**
     * @var string
     */
    public static $eventLoop = '';
    /**
     * @var callback
     */
    public static $onManagerReload = null;
    /**
     * @var callback
     */
    public static $onManagerStop = null;
    /**
     * @var array <Worker, Worker, ...>
     */
    public static $workers = [];
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
    public static $workerPidMap = [];
    /**
     * The format is like this
     * [
     *     pid1 => pid1,
     *     pid2 => pid2
     * ].
     * @var array
     */
    public static $pidsForReload = [];
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
    public static $wokerPids = [];

    /**
     * @var int
     */
    public static $status = Macro::STATUS_STARTING;
    /**
     * @var array
     */
    public static $builtinTransports = [
        'tcp' => 'tcp',
        'udp' => 'udp',
        'unix' => 'unix',
        'ssl' => 'tcp'
    ];

    /**
     * Run all worker instances.
     * @return void
     */
    public static function run()
    {
        self::preRun();
        ProcessUtil::parseCommand();
        ProcessUtil::daemonize(self::$daemonize);
        self::initWorkers();
        ProcessUtil::installSignal();
        ProcessUtil::saveManagerPid(1, self::$managerPidPath);
        self::forkWorkers();
        ProcessUtil::displayUI();
        ProcessUtil::resetStd();
        self::monitorWorkers();
    }

    /**
     * Get the event loop instance.
     * @return IEvent
     */
    public static function getEvent()
    {
        return self::$event;
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
                ProcessUtil::writeStatisticsToStatusFile();
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
     * Stop.
     * @return void
     */
    public static function stopAll()
    {
        self::$status = self::STATUS_SHUTDOWN;
        // For manager process.
        if (self::$managerPid === posix_getpid()) {
            ProcessUtil::log('phspring[' . basename(self::$startFile) . '] stopping ...');
            $pids = self::getAllWorkerPids();
            foreach ($pids as $pid) {
                posix_kill($pid, SIGINT);
                Timer::add(self::KILL_WORKER_TIMER_TIME, 'posix_kill', [$pid, SIGKILL], false);
            }
        } else { // For child processes.
            foreach (self::$workers as $worker) {
                $worker->stop();
            }
            self::$event->destroy();
            exit(0);
        }
    }

    /**
     * preRun.
     * @return void
     */
    protected static function preRun()
    {
        // Pid file.
        self::$managerPidPath = ProcessUtil::getPidSavePath();
        // ProcessUtil title.
        ProcessUtil::setTitle(Ac::config()->get('server.processTitle'));
        // Stats.
        ProcessUtil::setStatsData('startTime', time());
        // Init data for worker id.
        self::initId();
        // Timer init.
        Timer::init();
        // select a event
        self::$eventLoop = ProcessUtil::selectEventLoop(self::$eventLoop);
        // set status
        self::$status = Macro::STATUS_STARTING;
    }

    /**
     * @return void
     */
    public static function checkErrors()
    {
        if (self::STATUS_SHUTDOWN != self::$status) {
            $errMsg = 'WORKER EXIT UNEXPECTED ';
            $errors = error_get_last();
            if ($errors && ($errors['type'] === E_ERROR ||
                    $errors['type'] === E_PARSE ||
                    $errors['type'] === E_CORE_ERROR ||
                    $errors['type'] === E_COMPILE_ERROR ||
                    $errors['type'] === E_RECOVERABLE_ERROR)
            ) {
                $errMsg .= ProcessUtil::getErrorType($errors['type']) . " {$errors['message']} in {$errors['file']} on line {$errors['line']}";
            }
            self::log($errMsg);
        }
    }

    /**
     * @return void
     */
    protected static function initWorkers()
    {
        foreach (self::$workers as $worker) {
            if (empty($worker->name)) {
                $worker->name = 'nobody';
            }
            if (empty($worker->user)) {
                $worker->user = ProcessUtil::getUserName();
            } else {
                if (posix_getuid() !== 0 && $worker->user != ProcessUtil::getUserName()) {
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
    protected static function initId()
    {
        foreach (self::$workers as $workerId => $worker) {
            $ids = [];
            for ($i = 0; $i < $worker->count; $i++) {
                $ids[$i] = self::$wokerPids[$workerId][$i] ?? 0;
            }
            self::$wokerPids[$workerId] = $ids;
        }
    }

    /**
     * @return array
     */
    protected static function getAllWorkerPids()
    {
        $pids = [];
        foreach (self::$workerPidMap as $pids) {
            foreach ($pids as $pid) {
                $pids[$pid] = $pid;
            }
        }

        return $pids;
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

            $worker->count = max(1, $worker->count);
            while (count(self::$workerPidMap[$worker->workerId]) < $worker->count) {
                static::forkWorker($worker);
            }
        }
    }

    /**
     * @param Worker $worker
     * @throws Exception
     */
    protected static function forkWorker($worker)
    {
        $id = self::getId($worker->workerId, 0);
        if ($id === false) {
            return;
        }

        $pid = pcntl_fork();
        // manager process.
        if ($pid > 0) {
            self::$workerPidMap[$worker->workerId][$pid] = $pid;
            self::$wokerPids[$worker->workerId][$id] = $pid;
        } elseif (0 === $pid) { // child processes.
            if ($worker->reusePort) {
                $worker->listen();
            }
            if (self::$status === self::STATUS_STARTING) {
                ProcessUtil::resetStd();
            }
            self::$workerPidMap = [];
            self::$workers = [
                $worker->workerId => $worker
            ];
            Timer::delAll();
            ProcessUtil::setTitle('Server: worker process  ' . $worker->name . ' ' . $worker->getSocketName());
            $worker->setUserAndGroup();
            $worker->id = $id;
            $worker->run();
            $err = new \Exception('event-loop exited');
            self::log($err);
            exit(250);
        } else {
            throw new \Exception("Fork one worker failed.");
        }
    }

    /**
     * @param int $workerId
     * @param int $pid
     */
    protected static function getId($workerId, $pid)
    {
        return array_search($pid, self::$wokerPids[$workerId]);
    }

    /**
     * @return void
     */
    protected static function exitAndDestoryAll()
    {
        foreach (self::$workers as $worker) {
            $socketName = $worker->getSocketName();
            if ($worker->transport === 'unix' && $socketName) {
                list(, $address) = explode(':', $socketName, 2);
                @unlink($address);
            }
        }
        @unlink(self::$managerPidPath);
        self::log("Workerman[" . basename(self::$startFile) . "] has been stopped");
        if (self::$onManagerStop) {
            call_user_func(self::$onManagerStop);
        }

        exit(0);
    }

    /**
     * @return void
     */
    protected static function reload()
    {
        // For manager process.
        if (self::$managerPid === posix_getpid()) {
            if (self::$status !== self::STATUS_RELOADING && self::$status !== self::STATUS_SHUTDOWN) {
                self::log("PhSpring[" . basename(self::$startFile) . "] reloading");
                self::$status = self::STATUS_RELOADING;
                if (self::$onManagerReload) {
                    try {
                        call_user_func(self::$onManagerReload);
                    } catch (\Exception|\Error $e) {
                        self::log($e);
                        exit(250);
                    }
                    self::initId();
                }
            }

            $pidsReloadable = [];
            foreach (self::$workerPidMap as $workerId => $pids) {
                /* @var $worker Worker */
                $worker = self::$workers[$workerId];
                if ($worker->reloadable) {
                    foreach ($pids as $pid) {
                        $pidsReloadable[$pid] = $pid;
                    }
                } else {
                    foreach ($pids as $pid) {
                        posix_kill($pid, SIGUSR1);
                    }
                }
            }

            self::$pidsForReload = array_intersect(self::$pidsForReload, $pidsReloadable);

            if (empty(self::$pidsForReload)) {
                if (self::$status !== self::STATUS_SHUTDOWN) {
                    self::$status = self::STATUS_RUNNING;
                }
                return;
            }
            $currentPid = current(self::$pidsForReload);
            posix_kill($currentPid, SIGUSR1);
            Timer::add(self::KILL_WORKER_TIMER_TIME, 'posix_kill', [$currentPid, SIGKILL], false);
        } else { // For child processes.
            $worker = current(self::$workers);
            if ($worker->onWorkerReload) {
                try {
                    call_user_func($worker->onWorkerReload, $worker);
                } catch (\Exception|\Error $e) {
                    self::log($e);
                    exit(250);
                }
            }

            if ($worker->reloadable) {
                self::stopAll();
            }
        }
    }

    /**
     * @return void
     */
    protected static function monitorWorkers()
    {
        self::$status = self::STATUS_RUNNING;
        while (1) {
            pcntl_signal_dispatch();
            $status = 0;
            $pid = pcntl_wait($status, WUNTRACED);
            pcntl_signal_dispatch();
            if ($pid > 0) {
                foreach (self::$workerPidMap as $workerId => $pids) {
                    if (isset($pids[$pid])) {
                        $worker = self::$workers[$workerId];
                        if ($status !== 0) {
                            self::log("worker[" . $worker->name . ":$pid] exit with status $status");
                        }

                        if (!isset(self::$globalStatistics['worker_exit_info'][$workerId][$status])) {
                            self::$globalStatistics['worker_exit_info'][$workerId][$status] = 0;
                        }
                        self::$globalStatistics['worker_exit_info'][$workerId][$status]++;

                        unset(self::$workerPidMap[$workerId][$pid]);

                        $id = self::getId($workerId, $pid);
                        self::$wokerPids[$workerId][$id] = 0;

                        break;
                    }
                }
                if (self::$status !== self::STATUS_SHUTDOWN) {
                    self::forkWorkers();
                    if (isset(self::$pidsForReload[$pid])) {
                        unset(self::$pidsForReload[$pid]);
                        self::reload();
                    }
                } else {
                    if (!self::getAllWorkerPids()) {
                        self::exitAndDestoryAll();
                    }
                }
            } else {
                if (self::$status === self::STATUS_SHUTDOWN && !self::getAllWorkerPids()) {
                    self::exitAndDestoryAll();
                }
            }
        }
    }
}
