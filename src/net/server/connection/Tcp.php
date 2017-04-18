<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server\connection;

use phspring\net\server\event\IEvent;
use phspring\net\server\Macro;
use phspring\net\server\Manager;
use phspring\net\server\ProcessUtil;

/**
 * Class Tcp
 * @package phspring\net\server\connection
 */
class Tcp extends Connection
{
    /**
     * Read buffer size.
     * @var int
     */
    const READ_BUFFER_SIZE = 65535;
    /**
     * Status initial.
     * @var int
     */
    const STATUS_INITIAL = 0;
    /**
     * Status connecting.
     * @var int
     */
    const STATUS_CONNECTING = 1;
    /**
     * Status connection established.
     * @var int
     */
    const STATUS_ESTABLISH = 2;
    /**
     * Status closing.
     * @var int
     */
    const STATUS_CLOSING = 4;
    /**
     * Status closed.
     * @var int
     */
    const STATUS_CLOSED = 8;

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
     * Emitted when the send buffer becomes full.
     * @var callback
     */
    public $onBufferFull = null;
    /**
     * Emitted when the send buffer becomes empty.
     * @var callback
     */
    public $onBufferDrain = null;
    /**
     * Application layer protocol.
     * @var \phspring\net\server\protocol\IProtocol
     */
    public $protocol = null;
    /**
     * Transport (tcp/udp/unix/ssl).
     * @var string
     */
    public $transport = 'tcp';
    /**
     * Which worker belong to.
     * @var Worker
     */
    public $worker = null;
    /**
     * Connection->id.
     * @var int
     */
    public $id = 0;
    /**
     * A copy of $worker->id which used to clean up the connection in worker->connections
     * @var int
     */
    protected $_id = 0;
    /**
     * Sets the maximum send buffer size for the current connection.
     * OnBufferFull callback will be emited When the send buffer is full.
     * @var int
     */
    public $maxSendBufferSize = 1048576;
    /**
     * Default send buffer size.
     * @var int
     */
    public static $defaultMaxSendBufferSize = 1048576;
    /**
     * Maximum acceptable packet size.
     * @var int
     */
    public static $maxPackageSize = 10485760;
    /**
     * Id recorder.
     * @var int
     */
    protected static $idRecorder = 1;
    /**
     * Socket
     * @var resource
     */
    protected $socket = null;
    /**
     * Send buffer.
     * @var string
     */
    protected $sendBuffer = '';
    /**
     * Receive buffer.
     * @var string
     */
    protected $recvBuffer = '';
    /**
     * Current package length.
     * @var int
     */
    protected $currentPackageLength = 0;
    /**
     * Connection status.
     * @var int
     */
    protected $status = self::STATUS_ESTABLISH;
    /**
     * Remote address.
     * @var string
     */
    protected $remoteAddress = '';
    /**
     * Is paused.
     * @var bool
     */
    protected $isPaused = false;
    /**
     * SSL handshake completed or not
     * @var bool
     */
    protected $sslHandshakeCompleted = false;

    /**
     * Construct.
     *
     * @param resource $socket
     * @param string $remoteAddress
     */
    public function __construct($socket, $remoteAddress = '')
    {
        self::$statistics['connectionCount']++;
        $this->id = $this->id = self::$idRecorder++;
        $this->socket = $socket;
        stream_set_blocking($this->socket, 0);
        // Compatible with hhvm
        if (function_exists('stream_set_read_buffer')) {
            stream_set_read_buffer($this->socket, 0);
        }
        Manager::$globalEvent->add($this->socket, IEvent::EV_READ, [$this, 'baseRead']);
        $this->maxSendBufferSize = self::$defaultMaxSendBufferSize;
        $this->remoteAddress = $remoteAddress;
    }

    /**
     * Sends data on the connection.
     *
     * @param string $sendBuffer
     * @param bool $raw
     * @return void|bool|null
     */
    public function send($sendBuffer, $raw = false)
    {
        if ($this->status === self::STATUS_CLOSING || $this->status === self::STATUS_CLOSED) {
            return false;
        }

        // Try to call protocol::encode($sendBuffer) before sending.
        if (false === $raw && $this->protocol) {
            $parser = $this->protocol;
            $sendBuffer = $parser::encode($sendBuffer, $this);
            if ($sendBuffer === '') {
                return null;
            }
        }

        if ($this->status !== self::STATUS_ESTABLISH ||
            ($this->transport === 'ssl' && $this->sslHandshakeCompleted !== true)
        ) {
            if ($this->sendBuffer) {
                if ($this->bufferIsFull()) {
                    self::$statistics['sendFail']++;
                    return false;
                }
            }
            $this->sendBuffer .= $sendBuffer;
            $this->checkBufferWillFull();
            return null;
        }

        // Attempt to send data directly.
        if ($this->sendBuffer === '') {
            $len = @fwrite($this->socket, $sendBuffer);
            // send successful.
            if ($len === strlen($sendBuffer)) {
                return true;
            }
            // Send only part of the data.
            if ($len > 0) {
                $this->sendBuffer = substr($sendBuffer, $len);
            } else {
                // Connection closed?
                if (!is_resource($this->socket) || feof($this->socket)) {
                    self::$statistics['sendFail']++;
                    if ($this->onError) {
                        try {
                            call_user_func($this->onError, $this, Macro::PHSPRING_SEND_FAIL, 'client closed');
                        } catch (\Exception $e) {
                            ProcessUtil::log($e);
                            exit(250);
                        } catch (\Error $e) {
                            ProcessUtil::log($e);
                            exit(250);
                        }
                    }
                    $this->destroy();
                    return false;
                }
                $this->sendBuffer = $sendBuffer;
            }
            Manager::$globalEvent->add($this->socket, IEvent::EV_WRITE, [$this, 'baseWrite']);
            // Check if the send buffer will be full.
            $this->checkBufferWillFull();
            return null;
        } else {
            if ($this->bufferIsFull()) {
                self::$statistics['sendFail']++;
                return false;
            }

            $this->sendBuffer .= $sendBuffer;
            // Check if the send buffer is full.
            $this->checkBufferWillFull();
        }
    }

    /**
     * Get remote IP.
     *
     * @return string
     */
    public function getRemoteIp()
    {
        $pos = strrpos($this->remoteAddress, ':');
        if ($pos) {
            return trim(substr($this->remoteAddress, 0, $pos), '[]');
        }

        return '';
    }

    /**
     * Get remote port.
     *
     * @return int
     */
    public function getRemotePort()
    {
        if ($this->remoteAddress) {
            return (int)substr(strrchr($this->remoteAddress, ':'), 1);
        }

        return 0;
    }

    /**
     * Pauses the reading of data. That is onMessage will not be emitted. Useful to throttle back an upload.
     *
     * @return void
     */
    public function pauseRecv()
    {
        Manager::$globalEvent->del($this->socket, IEvent::EV_READ);
        $this->isPaused = true;
    }

    /**
     * Resumes reading after a call to pauseRecv.
     *
     * @return void
     */
    public function resumeRecv()
    {
        if ($this->isPaused === true) {
            Manager::$globalEvent->add($this->socket, IEvent::EV_READ, [$this, 'baseRead']);
            $this->isPaused = false;
            $this->baseRead($this->socket, false);
        }
    }

    /**
     * Base read handler.
     *
     * @param resource $socket
     * @param bool $checkEof
     * @return void
     */
    public function baseRead($socket, $checkEof = true)
    {
        // SSL handshake.
        if ($this->transport === 'ssl' && $this->sslHandshakeCompleted !== true) {
            $ret = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_SSLv2_SERVER |
                STREAM_CRYPTO_METHOD_SSLv3_SERVER | STREAM_CRYPTO_METHOD_SSLv23_SERVER);
            // Negotiation has failed.
            if (false === $ret) {
                if (!feof($socket)) {
                    echo "\nSSL Handshake fail. \nBuffer:" . bin2hex(fread($socket, 8182)) . "\n";
                }
                return $this->destroy();
            } elseif (0 === $ret) {
                // There isn't enough data and should try again.
                return;
            }
            if (isset($this->onSslHandshake)) {
                try {
                    call_user_func($this->onSslHandshake, $this);
                } catch (\Exception $e) {
                    self::log($e);
                    exit(250);
                } catch (\Error $e) {
                    self::log($e);
                    exit(250);
                }
            }
            $this->sslHandshakeCompleted = true;
            if ($this->sendBuffer) {
                Manager::$globalEvent->add($socket, IEvent::EV_WRITE, [$this, 'baseWrite']);
            }
            return;
        }

        $buffer = fread($socket, self::READ_BUFFER_SIZE);

        // Check connection closed.
        if ($buffer === '' || $buffer === false) {
            if ($checkEof && (feof($socket) || !is_resource($socket) || $buffer === false)) {
                $this->destroy();
                return;
            }
        } else {
            $this->recvBuffer .= $buffer;
        }

        // If the application layer protocol has been set up.
        if ($this->protocol) {
            $parser = $this->protocol;
            while ($this->recvBuffer !== '' && !$this->isPaused) {
                // The current packet length is known.
                if ($this->currentPackageLength) {
                    // Data is not enough for a package.
                    if ($this->currentPackageLength > strlen($this->recvBuffer)) {
                        break;
                    }
                } else {
                    // Get current package length.
                    $this->currentPackageLength = $parser::input($this->recvBuffer, $this);
                    // The packet length is unknown.
                    if ($this->currentPackageLength === 0) {
                        break;
                    } elseif ($this->currentPackageLength > 0 && $this->currentPackageLength <= self::$maxPackageSize) {
                        // Data is not enough for a package.
                        if ($this->currentPackageLength > strlen($this->recvBuffer)) {
                            break;
                        }
                    } // Wrong package.
                    else {
                        echo 'error package. package_length=' . var_export($this->currentPackageLength, true);
                        $this->destroy();
                        return;
                    }
                }

                // The data is enough for a packet.
                self::$statistics['totalRequest']++;
                // The current packet length is equal to the length of the buffer.
                if (strlen($this->recvBuffer) === $this->currentPackageLength) {
                    $one_request_buffer = $this->recvBuffer;
                    $this->recvBuffer = '';
                } else {
                    // Get a full package from the buffer.
                    $one_request_buffer = substr($this->recvBuffer, 0, $this->currentPackageLength);
                    // Remove the current package from the receive buffer.
                    $this->recvBuffer = substr($this->recvBuffer, $this->currentPackageLength);
                }
                // Reset the current packet length to 0.
                $this->currentPackageLength = 0;
                if (!$this->onMessage) {
                    continue;
                }
                try {
                    // Decode request buffer before Emitting onMessage callback.
                    call_user_func($this->onMessage, $this, $parser::decode($one_request_buffer, $this));
                } catch (\Exception $e) {
                    ProcessUtil::log($e);
                    exit(250);
                } catch (\Error $e) {
                    ProcessUtil::log($e);
                    exit(250);
                }
            }
            return;
        }

        if ($this->recvBuffer === '' || $this->isPaused) {
            return;
        }

        // Applications protocol is not set.
        self::$statistics['totalRequest']++;
        if (!$this->onMessage) {
            $this->recvBuffer = '';
            return;
        }
        try {
            call_user_func($this->onMessage, $this, $this->recvBuffer);
        } catch (\Exception $e) {
            ProcessUtil::log($e);
            exit(250);
        } catch (\Error $e) {
            ProcessUtil::log($e);
            exit(250);
        }
        // Clean receive buffer.
        $this->recvBuffer = '';
    }

    /**
     * Base write handler.
     *
     * @return void|bool
     */
    public function baseWrite()
    {
        $len = @fwrite($this->socket, $this->sendBuffer);
        if ($len === strlen($this->sendBuffer)) {
            Manager::$globalEvent->del($this->socket, IEvent::EV_WRITE);
            $this->sendBuffer = '';
            // Try to emit onBufferDrain callback when the send buffer becomes empty.
            if ($this->onBufferDrain) {
                try {
                    call_user_func($this->onBufferDrain, $this);
                } catch (\Exception $e) {
                    ProcessUtil::log($e);
                    exit(250);
                } catch (\Error $e) {
                    ProcessUtil::log($e);
                    exit(250);
                }
            }
            if ($this->status === self::STATUS_CLOSING) {
                $this->destroy();
            }
            return true;
        }
        if ($len > 0) {
            $this->sendBuffer = substr($this->sendBuffer, $len);
        } else {
            self::$statistics['sendFail']++;
            $this->destroy();
        }
    }

    /**
     * This method pulls all the data out of a readable stream, and writes it to the supplied destination.
     *
     * @param TcpConnection $dest
     * @return void
     */
    public function pipe($dest)
    {
        $source = $this;
        $this->onMessage = function ($source, $data) use ($dest) {
            $dest->send($data);
        };
        $this->onClose = function ($source) use ($dest) {
            $dest->destroy();
        };
        $dest->onBufferFull = function ($dest) use ($source) {
            $source->pauseRecv();
        };
        $dest->onBufferDrain = function ($dest) use ($source) {
            $source->resumeRecv();
        };
    }

    /**
     * Remove $length of data from receive buffer.
     *
     * @param int $length
     * @return void
     */
    public function consumeRecvBuffer($length)
    {
        $this->recvBuffer = substr($this->recvBuffer, $length);
    }

    /**
     * Close connection.
     *
     * @param mixed $data
     * @param bool $raw
     * @return void
     */
    public function close($data = null, $raw = false)
    {
        if ($this->status === self::STATUS_CLOSING || $this->status === self::STATUS_CLOSED) {
            return;
        } else {
            if ($data !== null) {
                $this->send($data, $raw);
            }
            $this->status = self::STATUS_CLOSING;
        }
        if ($this->sendBuffer === '') {
            $this->destroy();
        }
    }

    /**
     * Get the real socket.
     *
     * @return resource
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * Check whether the send buffer will be full.
     *
     * @return void
     */
    protected function checkBufferWillFull()
    {
        if ($this->maxSendBufferSize <= strlen($this->sendBuffer)) {
            if ($this->onBufferFull) {
                try {
                    call_user_func($this->onBufferFull, $this);
                } catch (\Exception $e) {
                    ProcessUtil::log($e);
                    exit(250);
                } catch (\Error $e) {
                    ProcessUtil::log($e);
                    exit(250);
                }
            }
        }
    }

    /**
     * Whether send buffer is full.
     *
     * @return bool
     */
    protected function bufferIsFull()
    {
        // Buffer has been marked as full but still has data to send then the packet is discarded.
        if ($this->maxSendBufferSize <= strlen($this->sendBuffer)) {
            if ($this->onError) {
                try {
                    call_user_func($this->onError, $this, Macro::PHSPRING_SEND_FAIL, 'send buffer full and drop package');
                } catch (\Exception $e) {
                    ProcessUtil::log($e);
                    exit(250);
                } catch (\Error $e) {
                    ProcessUtil::log($e);
                    exit(250);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Destroy connection.
     *
     * @return void
     */
    public function destroy()
    {
        // Avoid repeated calls.
        if ($this->status === self::STATUS_CLOSED) {
            return;
        }
        // Remove event listener.
        Manager::$globalEvent->del($this->socket, IEvent::EV_READ);
        Manager::$globalEvent->del($this->socket, IEvent::EV_WRITE);
        // Close socket.
        @fclose($this->socket);
        // Remove from worker->connections.
        if ($this->worker) {
            unset($this->worker->connections[$this->id]);
        }
        $this->status = self::STATUS_CLOSED;
        // Try to emit onClose callback.
        if ($this->onClose) {
            try {
                call_user_func($this->onClose, $this);
            } catch (\Exception $e) {
                ProcessUtil::log($e);
                exit(250);
            } catch (\Error $e) {
                ProcessUtil::log($e);
                exit(250);
            }
        }
        // Try to emit protocol::onClose
        if (method_exists($this->protocol, 'onClose')) {
            try {
                call_user_func([$this->protocol, 'onClose'], $this);
            } catch (\Exception $e) {
                ProcessUtil::log($e);
                exit(250);
            } catch (\Error $e) {
                ProcessUtil::log($e);
                exit(250);
            }
        }
        if ($this->status === self::STATUS_CLOSED) {
            // Cleaning up the callback to avoid memory leaks.
            $this->onMessage = $this->onClose = $this->onError = $this->onBufferFull = $this->onBufferDrain = null;
        }
    }

    /**
     * Destruct.
     *
     * @return void
     */
    public function __destruct()
    {
        self::$statistics['connectionCount']--;
    }
}
