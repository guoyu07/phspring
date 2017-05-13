<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server\base;

use phspring\net\server\Manager;
use phspring\net\server\Util;

/**
 * Class Worker
 * @package phspring\net\server\base
 */
abstract class Worker
{
    /**
     * class hash id.
     * @var string
     */
    public $workerId = '';
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
     * @param array $options
     */
    public function __construct($socketName = '', $options = [])
    {
        $this->workerId = spl_object_hash($this);
        $workers = Manager::getAllWorkers();
        Manager::$workers[$this->workerId] = $this;
        $workersPids = Manager::getAllWorkerPids();
        Manager::$workersPids[$this->workerId] = [];

        // Context for socket.
        if ($socketName) {
            $this->socketName = $socketName;
            if (!isset($options['socket']['backlog'])) {
                $options['socket']['backlog'] = Macro::DEFAULT_BACKLOG;
            }
            $this->socketContext = stream_context_create($options);
        }
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
            Util::log("Warning: User {$this->user} not exsits");
            return;
        }
        $uid = $user['uid'];
        // Get gid.
        if ($this->group) {
            $group = posix_getgrnam($this->group);
            if (!$group) {
                Util::log("Warning: Group {$this->group} not exsits");
                return;
            }
            $gid = $group['gid'];
        } else {
            $gid = $user['gid'];
        }

        // Set uid and gid.
        if ($uid != posix_getuid() || $gid != posix_getgid()) {
            if (!posix_setgid($gid) || !posix_initgroups($user['name'], $gid) || !posix_setuid($uid)) {
                Util::log("Warning: change gid or uid fail.");
            }
        }
    }

    /**
     * Listen port.
     * @throws Exception
     */
    abstract public function listen();

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
    abstract public function run();

    /**
     * Stop current worker instance.
     *
     * @return void
     */
    abstract public function stop();

    /**
     * Accept a connection.
     *
     * @param resource $socket
     * @return void
     */
    abstract public function acceptTcpConnection($socket);

    /**
     * For udp package.
     *
     * @param resource $socket
     * @return bool
     */
    abstract public function acceptUdpConnection($socket);

    /**
     * @param int $count
     */
    public function setCount($count)
    {
        $this->count = max(1, (int)$count);
    }
}
