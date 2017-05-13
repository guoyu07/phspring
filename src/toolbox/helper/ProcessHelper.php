<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\helper;

/**
 * Class ProcessHelper
 * @package phspring\toolbox\helper
 */
class ProcessHelper
{
    /**
     * Run as deamon mode.
     * @throws Exception
     */
    public static function daemonize()
    {
        umask(0);
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new \Exception('Fork fail');
        } elseif ($pid > 0) {
            exit(0);
        }
        if (-1 === posix_setsid()) {
            throw new \Exception("Set sid fail");
        }
        // Fork again avoid SVR4 system regain the control of terminal.
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new \Exception("Fork fail");
        } elseif (0 !== $pid) {
            exit(0);
        }
    }

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
     * Save pid.
     * @throws Exception
     */
    public static function savePid($pid, $path)
    {
        if (false === @file_put_contents($path, $pid)) {
            throw new \Exception('can not save pid to ' . $path);
        }
    }

    /**
     * Redirect standard input and output.
     *
     * @throws Exception
     */
    public static function resetStd($stdoutFile)
    {
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
     * Get unix user of current porcess.
     * @return string
     */
    public static function getUserName()
    {
        return posix_getpwuid(posix_getuid())['name'];
    }
}
