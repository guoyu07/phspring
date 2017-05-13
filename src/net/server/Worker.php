<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server;

use phspring\net\server\connection\Connection;
use phspring\net\server\connection\Tcp;
use phspring\net\server\connection\Udp;
use phspring\net\server\event\IEvent;

/**
 * Class Worker
 * @package phspring\net\server
 */
class Worker extends \phspring\net\server\base\Worker
{
    /**
     * Construct.
     *
     * @param string $socketName
     * @param array $options
     */
    public function __construct($socketName = '', $options = [])
    {
        parent::__construct($socketName, $options);
    }

    /**
     * Listen port.
     * @throws Exception
     */
    public function listen()
    {
        if (!$this->socketName || $this->mainSocket) {
            return;
        }

        // Get the application layer communication protocol and listening address.
        list($scheme, $address) = explode(':', $this->socketName, 2);
        // Check application layer protocol class.
        if (!isset(Manager::$defaultTransports[$scheme])) {
            if (class_exists($scheme)) {
                $this->protocol = $scheme;
            } else {
                $scheme = ucfirst($scheme);
                $this->protocol = '\\protocol\\' . $scheme;
                if (!class_exists($this->protocol)) {
                    $this->protocol = "\\phspring\\net\\server\\protocol\\$scheme";
                    if (!class_exists($this->protocol)) {
                        throw new \Exception("Class \\protocol\\$scheme not exist");
                    }
                }
            }
            if (!isset(Manager::$defaultTransports[$this->transport])) {
                throw new \Exception('Bad worker->transport ' . var_export($this->transport, true));
            }
        } else {
            $this->transport = $scheme;
        }

        $localSocket = Manager::$defaultTransports[$this->transport] . ":" . $address;

        // Flag.
        $flags = $this->transport === 'udp' ? STREAM_SERVER_BIND : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        $errno = 0;
        $errmsg = '';
        // SO_REUSEPORT.
        if ($this->reusePort) {
            stream_context_set_option($this->socketContext, 'socket', 'so_reuseport', 1);
        }

        // Create an Internet or Unix domain server socket.
        $this->mainSocket = stream_socket_server($localSocket, $errno, $errmsg, $flags, $this->socketContext);
        if (!$this->mainSocket) {
            throw new Exception($errmsg);
        }

        if ($this->transport === 'ssl') {
            stream_socket_enable_crypto($this->mainSocket, false);
        }

        // Try to open keepalive for tcp and disable Nagle algorithm.
        if (function_exists('socket_import_stream') && Manager::$defaultTransports[$this->transport] === 'tcp') {
            $socket = socket_import_stream($this->mainSocket);
            @socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
            @socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
        }

        // Non blocking.
        stream_set_blocking($this->mainSocket, 0);

        // Register a listener to be notified when server socket is ready to read.
        if (Manager::$event) {
            if ($this->transport === 'udp') {
                Manager::$event->add($this->mainSocket, IEvent::EV_READ,
                    [$this, 'acceptUdpConnection']);
            } else {
                Manager::$event->add($this->mainSocket, IEvent::EV_READ, [$this, 'acceptTcpConnection']);
            }
        }
    }

    /**
     * Run worker instance.
     *
     * @return void
     */
    public function run()
    {
        //Update process state.
        Manager::$status = Macro::STATUS_RUNNING;
        // Register shutdown function for checking errors.
        register_shutdown_function(["\\phspring\\net\\server\\Manager", 'checkErrors']);
        // Create a global event loop.
        if (!Manager::$event) {
            $class = Manager::getEvent(); // ???
            Manager::$event = new $class;
            // Register a listener to be notified when server socket is ready to read.
            if ($this->socketName) {
                if ($this->transport !== 'udp') {
                    Manager::$event->add($this->mainSocket, IEvent::EV_READ,
                        [$this, 'acceptTcpConnection']);
                } else {
                    Manager::$event->add($this->mainSocket, IEvent::EV_READ,
                        [$this, 'acceptUdpConnection']);
                }
            }
        }

        // Reinstall signal.
        Util::reinstallSignal();
        // Init Timer.
        Timer::init(Manager::$event);

        // Try to emit onWorkerStart callback.
        if ($this->onWorkerStart) {
            try {
                call_user_func($this->onWorkerStart, $this);
            } catch (\Exception|\Error $e) {
                Util::log($e);
                exit(250);
            }
        }

        // Main loop.
        Manager::$event->loop();
    }

    /**
     * Stop current worker instance.
     *
     * @return void
     */
    public function stop()
    {
        // Try to emit onWorkerStop callback.
        if ($this->onWorkerStop) {
            try {
                call_user_func($this->onWorkerStop, $this);
            } catch (\Exception|\Error $e) {
                Util::log($e);
                exit(250);
            }
        }
        // Remove listener for server socket.
        Manager::$event->del($this->mainSocket, IEvent::EV_READ);
        @fclose($this->mainSocket);
    }

    /**
     * Accept a connection.
     *
     * @param resource $socket
     * @return void
     */
    public function acceptTcpConnection($socket)
    {
        // Accept a connection on server socket.
        $newSocket = @stream_socket_accept($socket, 0, $remoteAddress);
        // Thundering herd.
        if (!$newSocket) {
            return;
        }

        // TcpConnection.
        $connection = new Tcp($newSocket, $remoteAddress);
        $this->connections[$connection->id] = $connection;
        $connection->worker = $this;
        $connection->protocol = $this->protocol;
        $connection->transport = $this->transport;
        $connection->onMessage = $this->onMessage;
        $connection->onClose = $this->onClose;
        $connection->onError = $this->onError;
        $connection->onBufferDrain = $this->onBufferDrain;
        $connection->onBufferFull = $this->onBufferFull;

        // Try to emit onConnect callback.
        if ($this->onConnect) {
            try {
                call_user_func($this->onConnect, $connection);
            } catch (\Exception|\Error $e) {
                Util::log($e);
                exit(250);
            }
        }
    }

    /**
     * For udp package.
     *
     * @param resource $socket
     * @return bool
     */
    public function acceptUdpConnection($socket)
    {
        $recvBuffer = stream_socket_recvfrom($socket, Macro::MAX_UDP_PACKAGE_SIZE, 0, $remoteAddress);
        if (false === $recvBuffer || empty($remoteAddress)) {
            return false;
        }
        // Udp connection.
        $connection = new Udp($socket, $remoteAddress);
        $connection->protocol = $this->protocol;
        if ($this->onMessage) {
            if ($this->protocol) {
                $parser = $this->protocol;
                $recvBuffer = $parser::decode($recvBuffer, $connection);
                // Discard bad packets.
                if ($recvBuffer === false) {
                    return true;
                }
            }
            Connection::$statistics['totalRequest']++;
            try {
                call_user_func($this->onMessage, $connection, $recvBuffer);
            } catch (\Exception|\Error $e) {
                Util::log($e);
                exit(250);
            }
        }

        return true;
    }
}
