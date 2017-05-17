<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server;

use phspring\net\server\base\Macro;
use phspring\net\server\base\Worker;
use phspring\net\server\event\Event;
use phspring\toolbox\helper\ProcessHelper;

/**
 * Class Manager
 * @package phspring\net\server
 */
class Manager extends \phspring\net\server\base\Manager
{
    /**
     * Run all worker instances.
     * @return void
     */
    public static function run()
    {
        parent::run();
    }

    /**
     * Parse command.
     * php server.php start|stop|restart|reload|status
     * @return void
     */
    public static function parseCommand()
    {
        global $argv;
        $file = $argv[0];
        $argv[1] = $argv[1] ?? 'start';

        // Get command.
        $command1 = trim($argv[1]);
        $command2 = $argv[2] ?? '';

        // Start command.
        $mode = '';
        if ($command1 === 'start') {
            if ($command2 === '-d' || self::$daemonize) {
                $mode = 'in daemon mode';
            } else {
                $mode = 'in debug mode';
            }
        }
        echo("phspring[$file] $command1 $mode " . PHP_EOL);

        // Get manager process PID.
        $managerPid = @file_get_contents(self::$pidPath);
        $managerIsAlive = $managerPid && @posix_kill($managerPid, 0);
        // Manager is still alive?
        if ($managerIsAlive) {
            if ($command1 === 'start' && posix_getpid() != $managerPid) {
                echo("phspring[$file] already running");
                exit;
            }
        } elseif ($command1 !== 'start' && $command1 !== 'restart') {
            echo("phspring[$file] not run");
            exit;
        }

        // execute command.
        switch ($command1) {
            case 'start':
                if ($command2 === '-d') {
                    self::$daemonize = true;
                }
                break;
            case 'status':
                if (is_file(self::$statFile)) {
                    @unlink(self::$statisticsFile);
                }
                // Manager process will send status signal to all child processes.
                posix_kill($managerPid, SIGUSR2);
                // Waiting amoment.
                usleep(500000);
                // Display statisitcs data from a disk file.
                @readfile(self::$statisticsFile);
                exit(0);
            case 'restart':
            case 'stop':
                echo("phspring[$file] is stoping ...");
                // Send stop signal to manager process.
                $managerPid && posix_kill($managerPid, SIGINT);
                // Timeout.
                $timeout = 5;
                $startTime = time();
                // Check manager process is still alive?
                while (1) {
                    $managerIsAlive = $managerPid && posix_kill($managerPid, 0);
                    if ($managerIsAlive) {
                        // check timeout
                        if (time() - $startTime >= $timeout) {
                            echo("phspring[$file] stop fail");
                            exit;
                        }
                        // Waiting amoment.
                        usleep(10000);
                        continue;
                    }
                    // Stop success.
                    echo("phspring[$file] stop success");
                    if ($command1 === 'stop') {
                        exit(0);
                    }
                    if ($command2 === '-d') {
                        self::$daemonize = true;
                    }
                    break;
                }
                break;
            case 'reload':
                posix_kill($managerPid, SIGUSR1);
                echo("phspring[$file] reload");
                exit;
            default :
                exit("Usage: php yourfile.php {start|stop|restart|reload|status}\n");
        }
    }

    /**
     * @return string
     */
    protected static function choiceEventLoop()
    {
        if (self::$eventName) {
            return self::$eventName;
        }

        $loop = '';
        foreach (self::$availableEventLoops as $name => $class) {
            if (extension_loaded($name)) {
                $loop = $name;
                break;
            }
        }
        if ($loop) {
            self::$eventName = self::$availableEventLoops[$loop];
        }

        return self::$eventName;
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
            if ($worker->getReusePort()) {
                $worker->listen();
            }
            if (self::$status === Macro::STATUS_STARTING) {
                self::resetStd();
            }
            //self::$workerPidMap = [];???
            self::$workers = [
                $worker->workerId => $worker
            ];
            Timer::delAll();
            ProcessHelper::setProcessTitle('Server: worker process  ' . $worker->name . ' ' . $worker->getSocketName());
            $worker->setUserAndGroup();
            $worker->id = $id;
            $worker->run();
            $err = new \Exception('event-loop exited');
            Util::log($err) && exit(250);
        } else {
            throw new \Exception("Fork one worker failed.");
        }
    }

    /**
     * @return void
     */
    protected static function reload()
    {
        // For manager process.
        if (self::$pid === posix_getpid()) {
            if (self::$status !== Macro::STATUS_RELOADING && self::$status !== Macro::STATUS_SHUTDOWN) {
                Util::log("phspring[" . basename(self::$startFile) . "] reloading");
                self::$status = Macro::STATUS_RELOADING;
                if (self::$onManagerReload) {
                    try {
                        call_user_func(self::$onManagerReload);
                    } catch (\Throwable $e) {
                        Util::log($e) && exit(250);
                    }
                    self::initWorkerPids();
                }
            }

            $pidsReloadable = [];
            //foreach (self::$workerPidMap as $workerId => $pids) {
            foreach (self::$workersPids as $workerId => $pids) {
                /* @var $worker Worker */
                $worker = self::$workers[$workerId];
                if ($worker->getReloadable()) {
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
                } catch (\Throwable $e) {
                    Util::log($e) && exit(250);
                }
            }

            if ($worker->getReloadable()) {
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
                        /* @var $worker Worker */
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

    /**
     * @return void
     */
    protected static function exitAndDestoryAll()
    {
        /* @var $worker Worker */
        foreach (self::$workers as $worker) {
            $socketName = $worker->getSocketName();
            if ($worker->transport === 'unix' && $socketName) {
                list(, $address) = explode(':', $socketName, 2);
                @unlink($address);
            }
        }
        @unlink(self::$pidPath);
        Util::log("phspring[" . basename(self::$startFile) . "] has been stopped");
        if (self::$onManagerStop) {
            call_user_func(self::$onManagerStop);
        }

        exit(0);
    }

    /**
     * @return mixed|string
     */
    protected static function setEventName()
    {
        if (self::$eventName) {
            return self::$eventName;
        }
        $loopName = '';
        $availables = [
            'libevent' => Libevent::class,
            'event' => Event::class
        ];
        foreach ($availables as $name => $class) {
            if (extension_loaded($name)) {
                $loopName = $name;
                break;
            }
        }

        if ($loopName) {
            $eventName = $availables[$loopName];
        } else {
            $eventName = '\phspring\net\server\event\Select';
        }

        return $eventName;
    }

    /**
     * Display staring UI.
     * @return void
     */
    protected static function displayUI()
    {
        self::safeEcho("\033[1A\n\033[K-----------------------\033[47;30m PhSpring \033[0m-----------------------------\n\033[0m");
        self::safeEcho('PhSpring version:' . AC::$version . "          PHP version:" . PHP_VERSION . "\n");
        self::safeEcho("------------------------\033[47;30m Workers \033[0m-------------------------------\n");
        self::safeEcho("\033[47;30muser\033[0m" . str_pad('',
                self::$maxUserNameLength + 2 - strlen('user')) . "\033[47;30mworker\033[0m" . str_pad('',
                self::$maxWorkerNameLength + 2 - strlen('worker')) . "\033[47;30mlisten\033[0m" . str_pad('',
                self::$maxSocketNameLength + 2 - strlen('listen')) . "\033[47;30mprocesses\033[0m \033[47;30m" . "status\033[0m\n");

        /* @var $worker Worker */
        foreach (self::$workers as $worker) {
            self::safeEcho(str_pad($worker->user, self::$maxUserNameLength + 2) . str_pad($worker->name,
                    self::$maxWorkerNameLength + 2) . str_pad($worker->getSocketName(),
                    self::$maxSocketNameLength + 2) . str_pad(' ' . $worker->getCount(),
                    9) . " \033[32;40m [OK] \033[0m\n");
        }
        self::safeEcho("----------------------------------------------------------------\n");
        if (self::$daemonize) {
            global $argv;
            self::safeEcho("Input \"php $argv[0] stop\" to quit. Start success.\n\n");
        } else {
            self::safeEcho("Press Ctrl-C to quit. Start success.\n");
        }
    }
}
