<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server;

/**
 * Class Macro
 * @package phspring\net\server
 */
class Macro
{
    /**
     * @var int
     */
    const PHSPRING_CONNECT_FAIL = 1;
    /**
     * @var int
     */
    const PHSPRING_SEND_FAIL = 2;

    /**
     * Status starting.
     * @var int
     */
    const STATUS_STARTING = 1;
    /**
     * Status running.
     * @var int
     */
    const STATUS_RUNNING = 2;
    /**
     * Status shutdown.
     * @var int
     */
    const STATUS_SHUTDOWN = 4;
    /**
     * Status reloading.
     * @var int
     */
    const STATUS_RELOADING = 8;
    /**
     * After sending the restart command to the child process KILL_WORKER_TIMER_TIME seconds,
     * if the process is still living then forced to kill.
     * @var int
     */
    const KILL_WORKER_TIMER_TIME = 2;
    /**
     * Default backlog. Backlog is the maximum length of the queue of pending connections.
     * @var int
     */
    const DEFAULT_BACKLOG = 102400;
    /**
     * Max udp package size.
     * @var int
     */
    const MAX_UDP_PACKAGE_SIZE = 65535;

    /**
     * @var string
     */
    const TRANSPORT_TCP = 'tcp';
    /**
     * @var string
     */
    const TRANSPORT_UDP = 'udp';
}
