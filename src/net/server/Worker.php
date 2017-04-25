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
class Worker
{
    /**
     * @var int
     */
    public $id = 0;
    /**
     * @var string
     */
    public $name = 'nobody';
    /**
     * @var int
     */
    public $count = 1;
    /**
     * Unix user of processes, needs appropriate privileges (usually root).
     * @var string
     */
    public $user = '';
    /**
     * Unix group of processes, needs appropriate privileges (usually root).
     * @var string
     */
    public $group = '';
    /**
     * reloadable.
     * @var bool
     */
    public $reloadable = true;
    /**
     * reuse port.
     * @var bool
     */
    public $reusePort = false;
    /**
     * Transport layer protocol.
     * @var string
     */
    public $transport = 'tcp';
    /**
     * Store all connections of clients.
     * @var array
     */
    public $connections = [];
    /**
     * Application layer protocol.
     * @var \phspring\net\server\protocol\IProtocol
     */
    public $protocol = null;
    /**
     * Emitted when worker processes start.
     * @var callback
     */
    public $onWorkerStart = null;
    /**
     * Emitted when a socket connection is successfully established.
     * @var callback
     */
    public $onConnect = null;
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
     * Emitted when worker processes stoped.
     * @var callback
     */
    public $onWorkerStop = null;
    /**
     * Emitted when worker processes get reload signal.
     * @var callback
     */
    public $onWorkerReload = null;

    /**
     * Root path for autoload.
     * @var string
     */
    protected $autoloadRootPath = '';
    /**
     * Listening socket.
     * @var resource
     */
    protected $mainSocket = null;
    /**
     * Socket name. The format is like this http://0.0.0.0:80 .
     * @var string
     */
    protected $socketName = '';
    /**
     * Context of socket.
     * @var resource
     */
    protected $socketContext = null;

    /**
     * Construct.
     *
     * @param string $socketName
     * @param array $contextOption
     */
    public function __construct($socketName = '', $contextOption = [])
    {
        // Save all worker instances.
        $this->workerId = spl_object_hash($this);
        Manager::$workers[$this->workerId] = $this;
        Manager::$wokerPids[$this->workerId] = [];

        // Get autoload root path.
        $backtrace = debug_backtrace();
        $this->autoloadRootPath = dirname($backtrace[0]['file']);

        // Context for socket.
        if ($socketName) {
            $this->socketName = $socketName;
            if (!isset($contextOption['socket']['backlog'])) {
                $context_option['socket']['backlog'] = Macro::DEFAULT_BACKLOG;
            }
            $this->socketContext = stream_context_create($contextOption);
        }

        // Set an empty onMessage callback.
        $this->onMessage = function () {
        };
    }

    /**
     * Set unix user and group for current process.
     *
     * @return void
     */
    public function setUserAndGroup()
    {
        // Get uid.
        $user = posix_getpwnam($this->user);
        if (!$user) {
            ProcessUtil::log("Warning: User {$this->user} not exsits");
            return;
        }
        $uid = $user['uid'];
        // Get gid.
        if ($this->group) {
            $group = posix_getgrnam($this->group);
            if (!$group) {
                ProcessUtil::log("Warning: Group {$this->group} not exsits");
                return;
            }
            $gid = $group['gid'];
        } else {
            $gid = $user['gid'];
        }

        // Set uid and gid.
        if ($uid != posix_getuid() || $gid != posix_getgid()) {
            if (!posix_setgid($gid) || !posix_initgroups($user['name'], $gid) || !posix_setuid($uid)) {
                ProcessUtil::log("Warning: change gid or uid fail.");
            }
        }
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

        // Autoload.
        Autoloader::setRootPath($this->autoloadRootPath);

        // Get the application layer communication protocol and listening address.
        list($scheme, $address) = explode(':', $this->socketName, 2);
        // Check application layer protocol class.
        if (!isset(Manager::$builtinTransports[$scheme])) {
            if (class_exists($scheme)) {
                $this->protocol = $scheme;
            } else {
                $scheme = ucfirst($scheme);
                $this->protocol = '\\protocol\\' . $scheme;
                if (!class_exists($this->protocol)) {
                    $this->protocol = "\\phspring\\net\\server\\protocol\\$scheme";
                    if (!class_exists($this->protocol)) {
                        throw new Exception("class \\protocol\\$scheme not exist");
                    }
                }
            }
            if (!isset(Manager::$builtinTransports[$this->transport])) {
                throw new \Exception('Bad worker->transport ' . var_export($this->transport, true));
            }
        } else {
            $this->transport = $scheme;
        }

        $localSocket = Manager::$builtinTransports[$this->transport] . ":" . $address;

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
        if (function_exists('socket_import_stream') && Manager::$builtinTransports[$this->transport] === 'tcp') {
            $socket = socket_import_stream($this->mainSocket);
            @socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
            @socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
        }

        // Non blocking.
        stream_set_blocking($this->mainSocket, 0);

        // Register a listener to be notified when server socket is ready to read.
        if (Manager::$event) {
            if ($this->transport !== 'udp') {
                Manager::$event->add($this->mainSocket, IEvent::EV_READ, [$this, 'acceptConnection']);
            } else {
                Manager::$event->add($this->mainSocket, IEvent::EV_READ,
                    [$this, 'acceptUdpConnection']);
            }
        }
    }

    /**
     * Get socket name.
     * @return string
     */
    public function getSocketName()
    {
        return $this->socketName ? lcfirst($this->socketName) : 'nobody';
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
        register_shutdown_function(["\\phspring\\net\\server\\Worker", 'checkErrors']);
        // Set autoload root path.
        Autoloader::setRootPath($this->autoloadRootPath);
        // Create a global event loop.
        if (!Manager::$event) {
            $class = Manager::getEvent();
            Manager::$event = new $class;
            // Register a listener to be notified when server socket is ready to read.
            if ($this->socketName) {
                if ($this->transport !== 'udp') {
                    Manager::$event->add($this->mainSocket, IEvent::EV_READ,
                        [$this, 'acceptConnection']);
                } else {
                    Manager::$event->add($this->mainSocket, IEvent::EV_READ,
                        [$this, 'acceptUdpConnection']);
                }
            }
        }

        // Reinstall signal.
        ProcessUtil::reinstallSignal();

        // Init Timer.
        Timer::init(Manager::$event);

        // Try to emit onWorkerStart callback.
        if ($this->onWorkerStart) {
            try {
                call_user_func($this->onWorkerStart, $this);
            } catch (\Exception|\Error $e) {
                ProcessUtil::log($e);
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
                ProcessUtil::log($e);
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
    public function acceptConnection($socket)
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
                ProcessUtil::log($e);
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
            Connection::$statistics['total_request']++;
            try {
                call_user_func($this->onMessage, $connection, $recvBuffer);
            } catch (\Exception|\Error $e) {
                ProcessUtil::log($e);
                exit(250);
            }
        }

        return true;
    }
}
