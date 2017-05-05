<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server;

use phspring\context\Ac;
use phspring\net\server\event\IEvent;

/**
 * Class Util
 * @package phspring\net\server
 */
class Util
{
    /**
     * @param $title
     */
    public static function setProcessTitle($title)
    {
        if (function_exists('cli_set_process_title')) {
            @cli_set_process_title($title);
        } else {
            throw new \Exception('Function of cli_set_process_title not exists.');
        }
    }

    /**
     * @return string
     */
    public static function getManagerPidSavePath()
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
     * @param string $eventLoop
     * @return string
     */
    public static function choiceEventLoop($eventLoop)
    {
        if ($eventLoop) {
            return $eventLoop;
        }

        $loopName = '';
        $availables = [
            'libevent' => '\phspring\net\server\event\Libevent',
            'event' => '\phspring\net\server\event\Event'
        ];
        foreach ($availables as $name => $class) {
            if (extension_loaded($name)) {
                $loopName = $name;
                break;
            }
        }

        if ($loopName) {
            $eventLoop = $availables[$loopName];
        } else {
            $eventLoop = '\phspring\net\server\event\Select';
        }

        return $eventLoop;
    }

    /**
     * Save pid.
     * @throws Exception
     */
    public static function saveManagerPid($pid, $path)
    {
        if (false === @file_put_contents($path, $pid)) {
            throw new \Exception('can not save pid to ' . $path);
        }
    }

    /**
     * @param string $key
     * @param mixed $val
     */
    public static function setStatsData($key, $val)
    {
        self::$statsData[$key] = $val;
    }

    /**
     * Parse command.
     * php server.php start|stop|restart|reload|status
     * @return void
     */
    public static function parseCommand()
    {
        global $argv;
        // Check argv;
        $startFile = $argv[0];
        if (!isset($argv[1])) {
            $argv[1] = 'start';
            exit("Usage: php AppServer.php {start|stop|restart|reload|status}\n");
        }

        // Get command.
        $command = trim($argv[1]);
        $command2 = $argv[2] ?? '';

        // Start command.
        $mode = '';
        if ($command === 'start') {
            if ($command2 === '-d' || self::$daemonize) {
                $mode = 'in DAEMON mode';
            } else {
                $mode = 'in DEBUG mode';
            }
        }
        echo("phspring[$startFile] $command $mode \n");

        // Get manager process PID.
        $managerPid = @file_get_contents(Manager::$managerPidPath);
        $managerIsAlive = $managerPid && @posix_kill($managerPid, 0);
        // Manager is still alive?
        if ($managerIsAlive) {
            if ($command === 'start' && posix_getpid() != $managerPid) {
                echo("phspring[$startFile] already running");
                exit;
            }
        } elseif ($command !== 'start' && $command !== 'restart') {
            echo("phspring[$startFile] not run");
            exit;
        }

        // execute command.
        switch ($command) {
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
                echo("phspring[$startFile] is stoping ...");
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
                            echo("phspring[$startFile] stop fail");
                            exit;
                        }
                        // Waiting amoment.
                        usleep(10000);
                        continue;
                    }
                    // Stop success.
                    echo("phspring[$startFile] stop success");
                    if ($command === 'stop') {
                        exit(0);
                    }
                    if ($command2 === '-d') {
                        Manager::$daemonize = true;
                    }
                    break;
                }
                break;
            case 'reload':
                posix_kill($managerPid, SIGUSR1);
                echo("phspring[$startFile] reload");
                exit;
            default :
                exit("Usage: php yourfile.php {start|stop|restart|reload|status}\n");
        }
    }

    /**
     * Run as deamon mode.
     * @throws Exception
     */
    public static function daemonize($daemonize)
    {
        if (!$daemonize) {
            return;
        }
        umask(0);
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new \Exception('fork fail');
        } elseif ($pid > 0) {
            exit(0);
        }
        if (-1 === posix_setsid()) {
            throw new \Exception("setsid fail");
        }
        // Fork again avoid SVR4 system regain the control of terminal.
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new \Exception("fork fail");
        } elseif (0 !== $pid) {
            exit(0);
        }
    }

    /**
     * Get unix user of current porcess.
     * @return string
     */
    public static function getUserName()
    {
        return posix_getpwuid(posix_getuid())['name'];
    }

    /**
     * Redirect standard input and output.
     *
     * @throws Exception
     */
    public static function resetStd($daemonize, $stdoutFile)
    {
        if (!$daemonize) {
            return;
        }
        global $STDOUT, $STDERR;
        $handle = fopen($stdoutFile, 'a');
        if ($handle) {
            unset($handle);
            @fclose(STDOUT);
            @fclose(STDERR);
            $STDOUT = fopen($stdoutFile, 'a');
            $STDERR = fopen($stdoutFile, 'a');
        } else {
            throw new \Exception('can not open stdoutFile ' . $stdoutFile);
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
        pcntl_signal(SIGINT, ["\\phspring\\net\\server\\Manager", 'signalHandler'], false);
        // reload
        pcntl_signal(SIGUSR1, ["\\phspring\\net\\server\\Manager", 'signalHandler'], false);
        // status
        pcntl_signal(SIGUSR2, ["\\phspring\\net\\server\\Manager", 'signalHandler'], false);
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
        $event->add(SIGINT, IEvent::EV_SIGNAL, ["\\phspring\\net\\server\\Manager", 'signalHandler']);
        // reinstall  reload signal handler
        $event->add(SIGUSR1, IEvent::EV_SIGNAL, ["\\phspring\\net\\server\\Manager", 'signalHandler']);
        // reinstall  status signal handler
        $event->add(SIGUSR2, IEvent::EV_SIGNAL, ["\\phspring\\net\\server\\Manager", 'signalHandler']);
    }

    /**
     * Safe Echo.
     * @param $msg
     */
    public static function safeEcho($msg)
    {
        if (!function_exists('posix_isatty') || posix_isatty(STDOUT)) {
            echo $msg;
        }
    }

    /**
     * Display staring UI.
     * @return void
     */
    public static function displayUI()
    {
        self::safeEcho("\033[1A\n\033[K-----------------------\033[47;30m PhSpring \033[0m-----------------------------\n\033[0m");
        self::safeEcho('PhSpring version:' . AC::$version . "          PHP version:" . PHP_VERSION . "\n");
        self::safeEcho("------------------------\033[47;30m Workers \033[0m-------------------------------\n");
        self::safeEcho("\033[47;30muser\033[0m" . str_pad('',
                self::$maxUserNameLength + 2 - strlen('user')) . "\033[47;30mworker\033[0m" . str_pad('',
                self::$maxWorkerNameLength + 2 - strlen('worker')) . "\033[47;30mlisten\033[0m" . str_pad('',
                self::$maxSocketNameLength + 2 - strlen('listen')) . "\033[47;30mprocesses\033[0m \033[47;30m" . "status\033[0m\n");

        foreach (self::$workers as $worker) {
            self::safeEcho(str_pad($worker->user, self::$maxUserNameLength + 2) . str_pad($worker->name,
                    self::$maxWorkerNameLength + 2) . str_pad($worker->getSocketName(),
                    self::$maxSocketNameLength + 2) . str_pad(' ' . $worker->count,
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

    /**
     * Get error message by error code.
     *
     * @param integer $type
     * @return string
     */
    public static function getErrorType($type)
    {
        switch ($type) {
            case E_ERROR: // 1 //
                return 'E_ERROR';
            case E_WARNING: // 2 //
                return 'E_WARNING';
            case E_PARSE: // 4 //
                return 'E_PARSE';
            case E_NOTICE: // 8 //
                return 'E_NOTICE';
            case E_CORE_ERROR: // 16 //
                return 'E_CORE_ERROR';
            case E_CORE_WARNING: // 32 //
                return 'E_CORE_WARNING';
            case E_COMPILE_ERROR: // 64 //
                return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING: // 128 //
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR: // 256 //
                return 'E_USER_ERROR';
            case E_USER_WARNING: // 512 //
                return 'E_USER_WARNING';
            case E_USER_NOTICE: // 1024 //
                return 'E_USER_NOTICE';
            case E_STRICT: // 2048 //
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR: // 4096 //
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED: // 8192 //
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED: // 16384 //
                return 'E_USER_DEPRECATED';
        }

        return '';
    }

    /**
     * Write statistics data to disk.
     *
     * @return void
     */
    public static function writeStatisticsToStatusFile()
    {
        // For manager process.
        if (Manager::$managerPid === posix_getpid()) {
            $loadavg = function_exists('sys_getloadavg') ? array_map('round', sys_getloadavg(), [2]) : [
                '-',
                '-',
                '-'
            ];
            file_put_contents(self::$statisticsFile,
                "---------------------------------------GLOBAL STATUS--------------------------------------------\n");
            file_put_contents(self::$statisticsFile,
                'PhSpring version:' . AC::$version . "          PHP version:" . PHP_VERSION . "\n", FILE_APPEND);
            file_put_contents(self::$statisticsFile, 'start time:' . date('Y-m-d H:i:s',
                    self::$globalStatistics['start_timestamp']) . '   run ' . floor((time() - self::$globalStatistics['start_timestamp']) / (24 * 60 * 60)) . ' days ' . floor(((time() - self::$globalStatistics['start_timestamp']) % (24 * 60 * 60)) / (60 * 60)) . " hours   \n",
                FILE_APPEND);
            $load_str = 'load average: ' . implode(", ", $loadavg);
            file_put_contents(self::$statisticsFile,
                str_pad($load_str, 33) . 'event-loop:' . Manager::getEvent() . "\n", FILE_APPEND);
            file_put_contents(self::$statisticsFile,
                count(self::$pidMap) . ' workers       ' . count(Manager::getAllWorkerPids()) . " processes\n",
                FILE_APPEND);
            file_put_contents(self::$statisticsFile,
                str_pad('worker_name', self::$maxWorkerNameLength) . " exit_status     exit_count\n", FILE_APPEND);
            foreach (self::$pidMap as $workerId => $pids) {
                $worker = self::$workers[$workerId];
                if (isset(self::$globalStatistics['worker_exit_info'][$workerId])) {
                    foreach (self::$globalStatistics['worker_exit_info'][$workerId] as $worker_exit_status => $worker_exit_count) {
                        file_put_contents(self::$statisticsFile,
                            str_pad($worker->name, self::$maxWorkerNameLength) . ' ' . str_pad($worker_exit_status,
                                16) . " $worker_exit_count\n", FILE_APPEND);
                    }
                } else {
                    file_put_contents(self::$statisticsFile,
                        str_pad($worker->name, self::$maxWorkerNameLength) . ' ' . str_pad(0, 16) . " 0\n",
                        FILE_APPEND);
                }
            }
            file_put_contents(self::$statisticsFile,
                "---------------------------------------PROCESS STATUS-------------------------------------------\n",
                FILE_APPEND);
            file_put_contents(self::$statisticsFile,
                "pid\tmemory  " . str_pad('listening', self::$maxSocketNameLength) . ' ' . str_pad('worker_name',
                    self::$maxWorkerNameLength) . " connections " . str_pad('total_request',
                    13) . ' ' . str_pad('send_fail', 9) . ' ' . str_pad('throw_exception', 15) . "\n", FILE_APPEND);

            chmod(self::$statisticsFile, 0722);

            foreach (self::getAllWorkerPids() as $pid) {
                posix_kill($pid, SIGUSR2);
            }
            return;
        }

        // For child processes.
        /** @var Worker $worker */
        $worker = current(self::$workers);
        $worker_status_str = posix_getpid() . "\t" . str_pad(round(memory_get_usage(true) / (1024 * 1024), 2) . 'M',
                7) . ' ' . str_pad($worker->getSocketName(),
                self::$maxSocketNameLength) . ' ' . str_pad(($worker->name === $worker->getSocketName() ? 'none' : $worker->name),
                self::$maxWorkerNameLength) . ' ';
        $worker_status_str .= str_pad(ConnectionInterface::$statistics['connection_count'],
                11) . ' ' . str_pad(ConnectionInterface::$statistics['total_request'],
                14) . ' ' . str_pad(ConnectionInterface::$statistics['send_fail'],
                9) . ' ' . str_pad(ConnectionInterface::$statistics['throw_exception'], 15) . "\n";
        file_put_contents(self::$statisticsFile, $worker_status_str, FILE_APPEND);
    }

    /**
     * Log.
     * @param string $msg
     * @return void
     */
    public static function log($msg)
    {
        $msg = $msg . "\n";
        if (!Manager::$daemonize) {
            self::safeEcho($msg);
        }
        file_put_contents((string)self::$logFile, date('Y-m-d H:i:s') . ' ' . 'pid:' . posix_getpid() . ' ' . $msg,
            FILE_APPEND | LOCK_EX);
    }
}
