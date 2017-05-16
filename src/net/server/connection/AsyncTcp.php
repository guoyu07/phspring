<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server\connection;

use phspring\net\server\event\IEvent;
use phspring\net\server\Macro;
use phspring\net\server\Manager;
use phspring\net\server\Util;
use phspring\net\server\Timer;

/**
 * Class AsyncTcp
 * @package phspring\net\server\connection
 */
class AsyncTcp extends Tcp
{
    /**
     * Emitted when socket connection is successfully established.
     * @var callback
     */
    public $onConnect = null;
    /**
     * Transport layer protocol.
     * @var string
     */
    public $transport = 'tcp';
    /**
     * Status.
     * @var int
     */
    protected $status = self::STATUS_INITIAL;
    /**
     * Remote host.
     * @var string
     */
    protected $remoteHost = '';
    /**
     * Connect start time.
     * @var string
     */
    protected $connectStartTime = 0;
    /**
     * Remote URI.
     * @var string
     */
    protected $remoteURI = '';
    /**
     * Context option.
     * @var resource
     */
    protected $contextOption = null;
    /**
     * Reconnect timer.
     * @var int
     */
    protected $reconnectTimer = null;
    /**
     * PHP default protocols.
     * @var array
     */
    protected static $defaultTransports = [
        'tcp' => 'tcp',
        'udp' => 'udp',
        'unix' => 'unix',
        'ssl' => 'ssl',
        'sslv2' => 'sslv2',
        'sslv3' => 'sslv3',
        'tls' => 'tls'
    ];

    /**
     * Construct.
     *
     * @param string $remoteAddress
     * @param array $contextOption
     * @throws \Exception
     */
    public function __construct($remoteAddress, $contextOption = null)
    {
        $addressInfo = parse_url($remoteAddress);
        if (!$addressInfo) {
            list($scheme, $this->remoteAddress) = explode(':', $remoteAddress, 2);
            if (!$this->remoteAddress) {
                echo new \Exception('Bad remote_address');
            }
        } else {
            if (!isset($addressInfo['port'])) {
                $addressInfo['port'] = 80;
            }
            if (!isset($addressInfo['path'])) {
                $addressInfo['path'] = '/';
            }
            if (!isset($addressInfo['query'])) {
                $addressInfo['query'] = '';
            } else {
                $addressInfo['query'] = '?' . $addressInfo['query'];
            }
            $this->remoteAddress = "{$addressInfo['host']}:{$addressInfo['port']}";
            $this->remoteHost = $addressInfo['host'];
            $this->remoteURI = "{$addressInfo['path']}{$addressInfo['query']}";
            $scheme = isset($addressInfo['scheme']) ? $addressInfo['scheme'] : 'tcp';
        }

        $this->id = self::$idRecorder++;
        // Check application layer protocol class.
        if (!isset(self::$defaultTransports[$scheme])) {
            $scheme = ucfirst($scheme);
            $this->protocol = '\\Protocols\\' . $scheme;
            if (!class_exists($this->protocol)) {
                $this->protocol = "\\phspring\\net\\server\\protocol\\$scheme";
                if (!class_exists($this->protocol)) {
                    throw new \Exception("class \\Protocols\\$scheme not exist");
                }
            }
        } else {
            $this->transport = self::$defaultTransports[$scheme];
        }

        // For statistics.
        self::$statistics['connectionCount']++;
        $this->maxSendBufferSize = self::$defaultMaxSendBufferSize;
        $this->contextOption = $contextOption;
    }

    /**
     * Do connect.
     *
     * @return void
     */
    public function connect()
    {
        if ($this->status !== self::STATUS_INITIAL && $this->status !== self::STATUS_CLOSING &&
            $this->status !== self::STATUS_CLOSED
        ) {
            return;
        }
        $this->status = self::STATUS_CONNECTING;
        $this->connectStartTime = microtime(true);
        // Open socket connection asynchronously.
        if ($this->contextOption) {
            $context = stream_context_create($this->contextOption);
            $this->socket = stream_socket_client("{$this->transport}://{$this->remoteAddress}", $errno, $errstr, 0,
                STREAM_CLIENT_ASYNC_CONNECT, $context);
        } else {
            $this->socket = stream_socket_client("{$this->transport}://{$this->remoteAddress}", $errno, $errstr, 0,
                STREAM_CLIENT_ASYNC_CONNECT);
        }
        // If failed attempt to emit onError callback.
        if (!$this->socket) {
            $this->emitError(Macro::PHSPRING_CONNECT_FAIL, $errstr);
            if ($this->status === self::STATUS_CLOSING) {
                $this->destroy();
            }
            if ($this->status === self::STATUS_CLOSED) {
                $this->onConnect = null;
            }
            return;
        }
        // Add socket to global event loop waiting connection is successfully established or faild.
        Manager::$event->add($this->socket, IEvent::EV_WRITE, [$this, 'checkConnection']);
    }

    /**
     * Reconnect.
     *
     * @param int $after
     * @return void
     */
    public function reConnect($after = 0)
    {
        $this->status = self::STATUS_INITIAL;
        if ($this->reconnectTimer) {
            Timer::del($this->reconnectTimer);
        }
        if ($after > 0) {
            $this->reconnectTimer = Timer::add($after, [$this, 'connect'], null, false);
            return;
        }

        return $this->connect();
    }

    /**
     * Get remote address.
     *
     * @return string
     */
    public function getRemoteHost()
    {
        return $this->remoteHost;
    }

    /**
     * Get remote URI.
     *
     * @return string
     */
    public function getRemoteURI()
    {
        return $this->remoteURI;
    }

    /**
     * Try to emit onError callback.
     *
     * @param int $code
     * @param string $msg
     * @return void
     */
    protected function emitError($code, $msg)
    {
        $this->status = self::STATUS_CLOSING;
        if ($this->onError) {
            try {
                call_user_func($this->onError, $this, $code, $msg);
            } catch (\Throwable $e) {
                Util::log($e) && exit(250);
            }
        }
    }

    /**
     * Check connection is successfully established or faild.
     *
     * @param resource $socket
     * @return void
     */
    public function checkConnection($socket)
    {
        // Check socket state.
        if ($address = stream_socket_get_name($socket, true)) {
            // Remove write listener.
            Manager::$event->del($socket, IEvent::EV_WRITE);
            // Nonblocking.
            stream_set_blocking($socket, 0);
            // Compatible with hhvm
            if (function_exists('stream_set_read_buffer')) {
                stream_set_read_buffer($socket, 0);
            }
            // Try to open keepalive for tcp and disable Nagle algorithm.
            if (function_exists('socket_import_stream') && $this->transport === 'tcp') {
                $raw_socket = socket_import_stream($socket);
                socket_set_option($raw_socket, SOL_SOCKET, SO_KEEPALIVE, 1);
                socket_set_option($raw_socket, SOL_TCP, TCP_NODELAY, 1);
            }
            // Register a listener waiting read event.
            Manager::$event->add($socket, IEvent::EV_READ, [$this, 'baseRead']);
            // There are some data waiting to send.
            if ($this->sendBuffer) {
                Manager::$event->add($socket, IEvent::EV_WRITE, [$this, 'baseWrite']);
            }
            $this->status = self::STATUS_ESTABLISH;
            $this->remoteAddress = $address;

            // Try to emit onConnect callback.
            if ($this->onConnect) {
                try {
                    call_user_func($this->onConnect, $this);
                } catch (\Throwable $e) {
                    Util::log($e) && exit(250);
                }
            }
            // Try to emit protocol::onConnect
            if (method_exists($this->protocol, 'onConnect')) {
                try {
                    call_user_func([$this->protocol, 'onConnect'], $this);
                } catch (\Throwable $e) {
                    Util::log($e) && exit(250);
                }
            }
        } else {
            // Connection failed.
            $this->emitError(Macro::PHSPRING_CONNECT_FAIL,
                'connect ' . $this->remoteAddress . ' fail after ' . round(microtime(true) - $this->connectStartTime,
                    4) . ' seconds');
            if ($this->status === self::STATUS_CLOSING) {
                $this->destroy();
            }
            if ($this->status === self::STATUS_CLOSED) {
                $this->onConnect = null;
            }
        }
    }
}
