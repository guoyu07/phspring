<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server\connection;

use phspring\net\server\base\Macro;
use phspring\net\server\event\IEvent;
use phspring\net\server\Manager;
use phspring\net\server\Util;
use phspring\net\server\timer\Timer;

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
     * Remote Uri.
     * @var string
     */
    protected $remoteUri = '';
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
     * @param string $remoteAddr
     * @param array $contextOption
     * @throws \Exception
     */
    public function __construct($remoteAddr, $contextOption = null)
    {
        $addrInfo = parse_url($remoteAddr);
        if (!$addrInfo) {
            list($scheme, $this->remoteAddr) = explode(':', $remoteAddr, 2);
            if (!$this->remoteAddr) {
                throw new \Exception('Bad remote_address');
            }
        } else {
            if (!isset($addrInfo['port'])) {
                $addrInfo['port'] = 80;
            }
            if (!isset($addrInfo['path'])) {
                $addrInfo['path'] = '/';
            }
            if (!isset($addrInfo['query'])) {
                $addrInfo['query'] = '';
            } else {
                $addrInfo['query'] = '?' . $addrInfo['query'];
            }
            $this->remoteAddr = "{$addrInfo['host']}:{$addrInfo['port']}";
            $this->remoteHost = $addrInfo['host'];
            $this->remoteURI = "{$addrInfo['path']}{$addrInfo['query']}";
            $scheme = isset($addrInfo['scheme']) ? $addrInfo['scheme'] : 'tcp';
        }

        $this->id = self::$idRecorder++;
        // Check application layer protocol class.
        if (!isset(self::$defaultTransports[$scheme])) {
            $scheme = ucfirst($scheme);
            $this->protocol = '\\protocols\\' . $scheme;
            if (!class_exists($this->protocol)) {
                $this->protocol = "\\phspring\\net\\server\\protocol\\$scheme";
                if (!class_exists($this->protocol)) {
                    throw new \Exception("class \\protocols\\$scheme not exist");
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
            $this->socket = stream_socket_client("{$this->transport}://{$this->remoteAddr}", $errno, $errMsg, 0,
                STREAM_CLIENT_ASYNC_CONNECT, $context);
        } else {
            $this->socket = stream_socket_client("{$this->transport}://{$this->remoteAddr}", $errno, $errMsg, 0,
                STREAM_CLIENT_ASYNC_CONNECT);
        }
        // If failed attempt to emit onError callback.
        if (!$this->socket) {
            $this->emitError(Macro::PHSPRING_CONNECT_FAIL, $errMsg);
            if ($this->status === self::STATUS_CLOSING) {
                $this->destroy();
            }
            if ($this->status === self::STATUS_CLOSED) {
                $this->onConnect = null;
            }

            return;
        }
        // Add socket to global event loop waiting connection is successfully established or faild.
        Manager::getGlobalEvent()->add($this->socket, IEvent::EV_WRITE, [$this, 'checkConnection']);
    }

    /**
     * Reconnect.
     *
     * @param int $after
     * @return void
     */
    public function reconnect($after = 0)
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
    public function getRemoteUri()
    {
        return $this->remoteUri;
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
        if ($address = stream_socket_get_name($socket, true)) {
            // Remove write listener.
            Manager::getGlobalEvent()->del($socket, IEvent::EV_WRITE);
            stream_set_blocking($socket, 0);
            // Try to open keepalive for tcp and disable Nagle algorithm.
            if (function_exists('socket_import_stream') && $this->transport === 'tcp') {
                $rawSocket = socket_import_stream($socket);
                socket_set_option($rawSocket, SOL_SOCKET, SO_KEEPALIVE, 1);
                socket_set_option($rawSocket, SOL_TCP, TCP_NODELAY, 1);
            }
            // Register a listener waiting read event.
            Manager::getGlobalEvent()->add($socket, IEvent::EV_READ, [$this, 'baseRead']);
            // There are some data waiting to send.
            if ($this->sendBuffer) {
                Manager::getGlobalEvent()->add($socket, IEvent::EV_WRITE, [$this, 'baseWrite']);
            }
            $this->status = self::STATUS_ESTABLISH;
            $this->remoteAddr = $address;

            if ($this->onConnect) {
                try {
                    call_user_func($this->onConnect, $this);
                } catch (\Throwable $e) {
                    Util::log($e) && exit(250);
                }
            }

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
                'connect ' . $this->remoteAddr . ' fail after ' . round(microtime(true) - $this->connectStartTime,
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
