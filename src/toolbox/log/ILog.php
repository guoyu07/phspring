<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\log;

/**
 * Class Log
 * @package phspring\toolbox\log
 */
interface ILog
{

    /**
     * init
     */
    public function init();

    /**
     * append log to notice log.
     */
    public function appendNoticeLog();

    /**
     * Adds a log record.（解决原始版本的进程崩溃问题）
     *
     * @param int $level The logging level
     * @param string $message The log message
     * @param array $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addRecord(int $level, string $message, array $context = []): bool;

    /**
     * get profile info.
     *
     * @return string
     */
    public function getAllProfileInfo();

    /**
     * for info level log only
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
