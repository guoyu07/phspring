<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server\connection;

/**
 * Class Connection
 * @package phspring\net\server\connection
 */
abstract class  Connection
{
    /**
     * Statistics for status command.
     * @var array
     */
    public static $statistics = [
        'connectionCount' => 0,
        'totalRequest' => 0,
        'throwException' => 0,
        'sendFail' => 0,
    ];
    /**
     * Emitted when data is received.
     * @var callback
     */
    public $onMessage = null;
    /**
     * Emitted when the other end of the socket sends a FIN packet.
     * @var callback
     */
    public $onClose = null;
    /**
     * Emitted when an error occurs with connection.
     * @var callback
     */
    public $onError = null;

    /**
     * Sends data on the connection.
     * @param string $send_buffer
     * @return void|boolean
     */
    abstract public function send($sendBuffer);

    /**
     * Get remote IP.
     * @return string
     */
    abstract public function getRemoteIp();

    /**
     * Get remote port.
     * @return int
     */
    abstract public function getRemotePort();

    /**
     * Close connection.
     * @param $data
     * @return void
     */
    abstract public function close($data = null);
}
