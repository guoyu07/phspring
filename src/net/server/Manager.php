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
    public static $managerPid = 0;
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
    public static $workersPids = [];
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
     * @var array
     */
    private static $_statsData = [];

    /**
     * Run all worker instances.
     * @return void
     */
    public static function run()
    {
        self::prepare();
        Util::parseCommand();
        Util::daemonize(self::$daemonize);
        self::setManagerPid();
        Util::saveManagerPid(1, self::$managerPidPath);
        self::initWorkers();
        Util::installSignal();
        self::forkWorkers();
        Util::displayUI();
        Util::resetStd();
        self::monitorWorkers();
    }

    /**
     * set manager pid
     * @param $pid
     */
    public static function setManagerPid()
    {
        self::$managerPid = posix_getpid();
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
     * Stop.
     * @return void
     */
    public static function stopAll()
    {
        self::$status = Macro::STATUS_SHUTDOWN;
        // For manager process.
        if (self::$managerPid === posix_getpid()) {
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
            self::$event->destroy();
            exit(0);
        }
    }

    /**
     * prepare.
     * @return void
     */
    protected static function prepare()
    {
        // Pid file.
        self::$managerPidPath = Util::getManagerPidSavePath();
        // Util title.
        Util::setProcessTitle(Ac::config()->get('server.processTitle'));
        // Stats.
        Util::setStatsData('startTime', time());
        // Init data for worker id.
        self::initWorkerPids();
        // Timer init.
        Timer::init();
        // select a event
        self::$eventLoop = Util::choiceEventLoop(self::$eventLoop);
        // set status
        self::$status = Macro::STATUS_STARTING;
    }

    /**
     * @return void
     */
    public static function checkErrors()
    {
        if (self::$status != Macro::STATUS_SHUTDOWN) {
            $errMsg = 'WORKER EXIT UNEXPECTED ';
            $errors = error_get_last();
            if ($errors && ($errors['type'] === E_ERROR ||
                    $errors['type'] === E_PARSE ||
                    $errors['type'] === E_CORE_ERROR ||
                    $errors['type'] === E_COMPILE_ERROR ||
                    $errors['type'] === E_RECOVERABLE_ERROR)
            ) {
                $errMsg .= Util::getErrorType($errors['type']) . " {$errors['message']} in {$errors['file']} on line {$errors['line']}";
            }
            Util::log($errMsg);
        }
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
                $worker->user = Util::getUserName();
            } else {
                if (posix_getuid() !== 0 && $worker->user != Util::getUserName()) {
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
     * fork one worker
     * @param Worker $worker
     * @throws Exception
     */
    protected static function forkWorker($worker)
    {
        //$id = self::getId($worker->workerId, 0);
        $id = array_search(0, self::$workersPids[$worker->workerId]);
        if ($id === false) {
            return;
        }

        $pid = pcntl_fork();
        // manager process.
        if ($pid > 0) {
            //self::$workerPidMap[$worker->workerId][$pid] = $pid;
            self::$workersPids[$worker->workerId][$id] = $pid;
        } elseif (0 === $pid) { // child processes.
            if ($worker->reusePort) {
                $worker->listen();
            }
            if (self::$status === Macro::STATUS_STARTING) {
                Util::resetStd();
            }
            //self::$workerPidMap = [];???
            self::$workers = [
                $worker->workerId => $worker
            ];
            Timer::delAll();
            Util::setProcessTitle('Server: worker process  ' . $worker->name . ' ' . $worker->getSocketName());
            $worker->setUserAndGroup();
            $worker->id = $id;
            $worker->run();
            $err = new \Exception('event-loop exited');
            Util::log($err);
            exit(250);
        } else {
            throw new \Exception("Fork one worker failed.");
        }
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
        Util::log("phspring[" . basename(self::$startFile) . "] has been stopped");
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
            if (self::$status !== Macro::STATUS_RELOADING && self::$status !== Macro::STATUS_SHUTDOWN) {
                Util::log("phspring[" . basename(self::$startFile) . "] reloading");
                self::$status = Macro::STATUS_RELOADING;
                if (self::$onManagerReload) {
                    try {
                        call_user_func(self::$onManagerReload);
                    } catch (\Exception|\Error $e) {
                        Util::log($e);
                        exit(250);
                    }
                    self::initWorkerPids();
                }
            }

            $pidsReloadable = [];
            //foreach (self::$workerPidMap as $workerId => $pids) {
            foreach (self::$workersPids as $workerId => $pids) {
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
                if (self::$status !== Macro::STATUS_SHUTDOWN) {
                    self::$status = Macro::STATUS_RUNNING;
                }
                return;
            }
            $currentPid = current(self::$pidsForReload);
            posix_kill($currentPid, SIGUSR1);
            Timer::add(Macro::KILL_WORKER_TIMER_TIME, 'posix_kill', [$currentPid, SIGKILL], false);
        } else { // For child processes.
            /* @var $worker Worker */
            $worker = current(self::$workers);
            if ($worker->onWorkerReload) {
                try {
                    call_user_func($worker->onWorkerReload, $worker);
                } catch (\Exception|\Error $e) {
                    Util::log($e);
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
        self::$status = Macro::STATUS_RUNNING;
        while (1) {
            pcntl_signal_dispatch();
            $status = 0;
            $pid = pcntl_wait($status, WUNTRACED);
            pcntl_signal_dispatch();
            if ($pid > 0) {
                // foreach (self::$workerPidMap as $workerId => $pids) {
                foreach (self::$workersPids as $workerId => $pids) {
                    // if (isset($pids[$pid])) {
                    if (in_array($pid, $pids)) {
                        $worker = self::$workers[$workerId];
                        if ($status !== 0) {
                            Util::log("worker[" . $worker->name . ":$pid] exit with status $status");
                        }

                        if (!isset(self::$globalStatistics['worker_exit_info'][$workerId][$status])) {
                            self::$globalStatistics['worker_exit_info'][$workerId][$status] = 0;
                        }
                        self::$globalStatistics['worker_exit_info'][$workerId][$status]++;

                        // unset(self::$workerPidMap[$workerId][$pid]);
                        self::$workersPids[$workerId][array_search($pid, $pids)] = 0;
                        break;
                    }
                }
                if (self::$status !== Macro::STATUS_SHUTDOWN) {
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
                if (self::$status === Macro::STATUS_SHUTDOWN && !self::getAllWorkerPids()) {
                    self::exitAndDestoryAll();
                }
            }
        }
    }
}
