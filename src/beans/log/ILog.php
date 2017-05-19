<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\beans\log;

/**
 * Class Log
 * @package phspring\beans\log
 */
interface ILog
{

    /**
     * @param $message The log message
     * @param array $context The log context
     * @return mixed
     */
    public function notice($message, array $context = []);

    /**
     * @param $message The log message
     * @param array $context The log context
     * @return mixed
     */
    public function info($message, array $context = []);

    /**
     * @param $message The log message
     * @param array $context The log context
     * @return mixed
     */
    public function warning($message, array $context = []);

    /**
     * @param $message The log message
     * @param array $context The log context
     * @return mixed
     */
    public function error($message, array $context = []);

    /**
     * push log to notice.
     *
     * @param string|number $key
     * @param string $val
     */
    public function pushLog($key, $val = '');

    /**
     * profile start
     *
     * @param string $name
     */
    public function profileStart($name);

    /**
     * profile end
     *
     * @param string $name
     */
    public function profileEnd($name);

    /**
     * @param $name
     * @param $cost
     */
    public function profile($name, $cost);

    /**
     * for counting
     *
     * @param string $name
     * @param int $hit
     * @param int $total
     */
    public function counting($name, $hit, $total = null);
}
