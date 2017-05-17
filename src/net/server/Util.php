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
     * log file
     * @var string
     */
    private static $_logFile = '';
    /**
     * statistics data
     * @var array
     */
    private static $_statsData = [];

    /**
     * set phspring log file
     * @param $file
     */
    public static function setLogFile($file)
    {
        if (!is_dir(dirname($file))) {
            exit('Log file dir ' . dirname($file) . ' not exists.');
        }
        self::$_logFile = $file;
    }

    /**
     * @param string $key
     * @param mixed $val
     */
    public static function setStatsData($key, $val)
    {
        self::$_statsData[$key] = $val;
    }

    /**
     * Log.
     * @param string $msg
     * @return void
     */
    public static function log($msg)
    {
        $msg .= PHP_EOL;
        if (!Manager::getDaemonize() && !function_exists('posix_isatty') || posix_isatty(STDOUT)) {
            echo $msg;
        }
        file_put_contents(self::$_logFile, date('Y-m-d H:i:s') . ' ' . 'pid:' . posix_getpid() . ' ' . $msg,
            FILE_APPEND | LOCK_EX);

        return true;
    }

    /**
     * Write statistics data to disk.
     *
     * @return void
     */
    public static function writeStatisticsToStatusFile()
    {
        // For manager process.
        if (Manager::getPid() === posix_getpid()) {

        } else {

        }
    }
}
