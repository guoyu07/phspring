<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server\connection;

/**
 * Class Udp
 * @package phspring\net\server\connection
 */
class Udp
{
    /**
     * Application layer protocol.
     * The format is like this Workerman\\Protocols\\Http.
     *
     * @var \Workerman\Protocols\ProtocolInterface
     */
    public $protocol = null;

    /**
     * Udp socket.
     * @var resource
     */
    protected $socket = null;

    /**
     * Remote address.
     * @var string
     */
    protected $remoteAddr = '';

    /**
     * Construct.
     * @param resource $socket
     * @param string $remote_address
     */
    public function __construct($socket, $remoteAddr)
    {
        $this->socket = $socket;
        $this->remoteAddr = $remoteAddr;
    }

    /**
     * Sends data on the connection.
     * @param string $send_buffer
     * @param bool $raw
     * @return void|boolean
     */
    public function send($sendBuffer, $raw = false)
    {
        if (false === $raw && $this->protocol) {
            $parser = $this->protocol;
            $sendBuffer = $parser::encode($sendBuffer, $this);
            if ($sendBuffer === '') {
                return null;
            }
        }

        return strlen($sendBuffer) === stream_socket_sendto($this->socket, $sendBuffer, 0, $this->remoteAddr);
    }

    /**
     * Get remote IP.
     * @return string
     */
    public function getRemoteIp()
    {
        $pos = strrpos($this->remoteAddr, ':');
        if ($pos) {
            return trim(substr($this->remoteAddr, 0, $pos), '[]');
        }
        return '';
    }

    /**
     * Get remote port.
     * @return int
     */
    public function getRemotePort()
    {
        if ($this->remoteAddr) {
            return (int)substr(strrchr($this->remoteAddr, ':'), 1);
        }

        return 0;
    }

    /**
     * Close connection.
     * @param mixed $data
     * @param bool $raw
     * @return bool
     */
    public function close($data = null, $raw = false)
    {
        if ($data !== null) {
            $this->send($data, $raw);
        }

        return true;
    }
}
