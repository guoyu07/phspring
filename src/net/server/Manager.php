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
     * The PID of manager process.
     * @var int
     */
    protected static $managerPid = 0;
    /**
     * The file path to store manager process PID.
     * @var string
     */
    public static $managerPidPath = '';

    /**
     * Global event loop.
     * @var event\IEvent
     */
    public static $globalEvent = null;
    /**
     * EventLoopClass
     * @var string
     */
    public static $eventLoop = '';
    /**
     * Emitted when the manager process get reload signal.
     * @var callback
     */
    public static $onManagerReload = null;
    /**
     * Emitted when the manager process terminated.
     * @var callback
     */
    public static $onManagerStop = null;
    /**
     * All worker instances.
     * @var array
     */
    protected static $workers = [];
    /**
     * All worker porcesses pid.
     * The format is like this [workerId=>[pid=>pid, pid=>pid, ..], ..]
     * @var array
     */
    protected static $workerPidMap = [];
    /**
     * All worker processes waiting for restart.
     * The format is like this [pid=>pid, pid=>pid].
     * @var array
     */
    protected static $pidsToRestart = [];
    /**
     * Mapping from PID to worker process ID.
     * The format is like this [workerId=>[0=>$pid, 1=>$pid, ..], ..].
     * @var array
     */
    protected static $idMap = [];
    /**
     * Current status.
     * @var int
     */
    protected static $status = self::STATUS_STARTING;
    /**
     * PHP built-in protocols.
     * @var array
     */
    protected static $builtinTransports = [
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
     * Init All worker instances.
     * @return void
     */
    protected static function initWorkers()
    {
        foreach (self::$workers as $worker) {
            // Worker name.
            if (empty($worker->name)) {
                $worker->name = 'none';
            }
            // Get unix user of the worker process.
            if (empty($worker->user)) {
                $worker->user = ProcessUtil::getCurrentUserName();
            } else {
                if (posix_getuid() !== 0 && $worker->user != ProcessUtil::getCurrentUserName()) {
                    echo 'Warning: You must have the root privileges to change uid and gid.';
                }
            }
            // Listen.
            if (!$worker->reusePort) {
                $worker->listen();
            }
        }
    }

    /**
     * Get global event-loop instance.
     *
     * @return IEvent
     */
    public static function getEventLoop()
    {
        return self::$globalEvent;
    }

    /**
     * Init idMap.
     * return void
     */
    protected static function initId()
    {
        foreach (self::$workers as $workerId => $worker) {
            $newIdMap = [];
            for ($key = 0; $key < $worker->count; $key++) {
                $newIdMap[$key] = isset(self::$idMap[$workerId][$key]) ? self::$idMap[$workerId][$key] : 0;
            }
            self::$idMap[$workerId] = $newIdMap;
        }
    }

    /**
     * Signal handler.
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
                self::$pidsToRestart = self::getAllWorkerPids();
                self::reload();
                break;
            // Show status.
            case SIGUSR2:
                ProcessUtil::writeStatisticsToStatusFile();
                break;
        }
    }

    /**
     * Get all worker instances.
     * @return array
     */
    public static function getAllWorkers()
    {
        return self::$workers;
    }

    /**
     * Get all pids of worker processes.
     * @return array
     */
    protected static function getAllWorkerPids()
    {
        $pids = [];
        foreach (self::$workerPidMap as $pids) {
            foreach ($pids as $workId) {
                $pids[$workId] = $workId;
            }
        }

        return $pids;
    }

    /**
     * Fork some worker processes.
     * @return void
     */
    protected static function forkWorkers()
    {
        foreach (self::$workers as $worker) {
            if (self::$status === self::STATUS_STARTING) {
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
     * Fork one worker process.
     * @param Worker $worker
     * @throws Exception
     */
    protected static function forkWorker($worker)
    {
        // Get available worker id.
        $id = self::getId($worker->workerId, 0);
        if ($id === false) {
            return;
        }
        $pid = pcntl_fork();
        // For manager process.
        if ($pid > 0) {
            self::$workerPidMap[$worker->workerId][$pid] = $pid;
            self::$idMap[$worker->workerId][$id] = $pid;
        } elseif (0 === $pid) { // For child processes.
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
     * Get worker id.
     * @param int $workerId
     * @param int $pid
     */
    protected static function getId($workerId, $pid)
    {
        return array_search($pid, self::$idMap[$workerId]);
    }

    /**
     * Monitor all child processes.
     * @return void
     */
    protected static function monitorWorkers()
    {
        self::$status = self::STATUS_RUNNING;
        while (1) {
            // Calls signal handlers for pending signals.
            pcntl_signal_dispatch();
            // Suspends execution of the current process until a child has exited, or until a signal is delivered
            $status = 0;
            $pid = pcntl_wait($status, WUNTRACED);
            // Calls signal handlers for pending signals again.
            pcntl_signal_dispatch();
            // If a child has already exited.
            if ($pid > 0) {
                // Find out witch worker process exited.
                foreach (self::$workerPidMap as $workerId => $workerPids) {
                    if (isset($workerPids[$pid])) {
                        $worker = self::$workers[$workerId];
                        // Exit status.
                        if ($status !== 0) {
                            self::log("worker[" . $worker->name . ":$pid] exit with status $status");
                        }

                        // For Statistics.
                        if (!isset(self::$globalStatistics['worker_exit_info'][$workerId][$status])) {
                            self::$globalStatistics['worker_exit_info'][$workerId][$status] = 0;
                        }
                        self::$globalStatistics['worker_exit_info'][$workerId][$status]++;

                        // Clear process data.
                        unset(self::$workerPidMap[$workerId][$pid]);

                        // Mark id is available.
                        $id = self::getId($workerId, $pid);
                        self::$idMap[$workerId][$id] = 0;

                        break;
                    }
                }
                // Is still running state then fork a new worker process.
                if (self::$status !== self::STATUS_SHUTDOWN) {
                    self::forkWorkers();
                    // If reloading continue.
                    if (isset(self::$pidsToRestart[$pid])) {
                        unset(self::$pidsToRestart[$pid]);
                        self::reload();
                    }
                } else {
                    // If shutdown state and all child processes exited then manager process exit.
                    if (!self::getAllWorkerPids()) {
                        self::exitAndDestoryAll();
                    }
                }
            } else {
                // If shutdown state and all child processes exited then manager process exit.
                if (self::$status === self::STATUS_SHUTDOWN && !self::getAllWorkerPids()) {
                    self::exitAndDestoryAll();
                }
            }
        }
    }

    /**
     * Exit current process.
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
     * Execute reload.
     * @return void
     */
    protected static function reload()
    {
        // For manager process.
        if (self::$managerPid === posix_getpid()) {
            // Set reloading state.
            if (self::$status !== self::STATUS_RELOADING && self::$status !== self::STATUS_SHUTDOWN) {
                self::log("Workerman[" . basename(self::$startFile) . "] reloading");
                self::$status = self::STATUS_RELOADING;
                // Try to emit onManagerReload callback.
                if (self::$onManagerReload) {
                    try {
                        call_user_func(self::$onManagerReload);
                    } catch (\Exception $e) {
                        self::log($e);
                        exit(250);
                    } catch (\Error $e) {
                        self::log($e);
                        exit(250);
                    }
                    self::initId();
                }
            }

            // Send reload signal to all child processes.
            $reloadable_pid_array = [];
            foreach (self::$workerPidMap as $workerId => $workerPids) {
                $worker = self::$workers[$workerId];
                if ($worker->reloadable) {
                    foreach ($workerPids as $pid) {
                        $reloadable_pid_array[$pid] = $pid;
                    }
                } else {
                    foreach ($workerPids as $pid) {
                        // Send reload signal to a worker process which reloadable is false.
                        posix_kill($pid, SIGUSR1);
                    }
                }
            }

            // Get all pids that are waiting reload.
            self::$pidsToRestart = array_intersect(self::$pidsToRestart, $reloadable_pid_array);

            // Reload complete.
            if (empty(self::$pidsToRestart)) {
                if (self::$status !== self::STATUS_SHUTDOWN) {
                    self::$status = self::STATUS_RUNNING;
                }
                return;
            }
            // Continue reload.
            $one_worker_pid = current(self::$pidsToRestart);
            // Send reload signal to a worker process.
            posix_kill($one_worker_pid, SIGUSR1);
            // If the process does not exit after self::KILL_WORKER_TIMER_TIME seconds try to kill it.
            Timer::add(self::KILL_WORKER_TIMER_TIME, 'posix_kill', [$one_worker_pid, SIGKILL], false);
        } else { // For child processes.
            $worker = current(self::$workers);
            // Try to emit onWorkerReload callback.
            if ($worker->onWorkerReload) {
                try {
                    call_user_func($worker->onWorkerReload, $worker);
                } catch (\Exception $e) {
                    self::log($e);
                    exit(250);
                } catch (\Error $e) {
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
     * Stop.
     * @return void
     */
    public static function stopAll()
    {
        self::$status = self::STATUS_SHUTDOWN;
        // For manager process.
        if (self::$managerPid === posix_getpid()) {
            self::log("Workerman[" . basename(self::$startFile) . "] Stopping ...");
            $workerPids = self::getAllWorkerPids();
            // Send stop signal to all child processes.
            foreach ($workerPids as $workerPid) {
                posix_kill($workerPid, SIGINT);
                Timer::add(self::KILL_WORKER_TIMER_TIME, 'posix_kill', [$workerPid, SIGKILL], false);
            }
        } else { // For child processes.
            // Execute exit.
            foreach (self::$workers as $worker) {
                $worker->stop();
            }
            self::$globalEvent->destroy();
            exit(0);
        }
    }

    /**
     * Check errors when current process exited.
     * @return void
     */
    public static function checkErrors()
    {
        if (self::STATUS_SHUTDOWN != self::$status) {
            $errMsg = "WORKER EXIT UNEXPECTED ";
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
}
