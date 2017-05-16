<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server;

/**
 * Class Util
 * @package phspring\net\server
 */
class Util
{
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
     * @param string $key
     * @param mixed $val
     */
    public static function setStatsData($key, $val)
    {
        self::$statsData[$key] = $val;
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

        return true;
    }
}
