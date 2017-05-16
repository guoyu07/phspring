<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server\base;

use phspring\net\server\Util;

/**
 * Class Worker
 * @package phspring\net\server\base
 */
abstract class Worker
{
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
     * class hash id.
     * @var string
     */
    protected $workerId = '';
    /**
     * @var int
     */
    protected $id = 0;
    /**
     * @var string
     */
    protected $name = 'nobody';
    /**
     * @var int
     */
    protected $count = 1;
    /**
     * Unix user of processes, needs appropriate privileges (usually root).
     * @var string
     */
    protected $user = '';
    /**
     * Unix group of processes, needs appropriate privileges (usually root).
     * @var string
     */
    protected $group = '';
    /**
     * reloadable.
     * @var bool
     */
    protected $reloadable = true;
    /**
     * reuse port.
     * @var bool
     */
    protected $reusePort = false;
    /**
     * Transport layer protocol.
     * @var string
     */
    protected $transport = 'tcp';
    /**
     * Store all connections of clients.
     * @var array
     */
    protected $connections = [];
    /**
     * Application layer protocol.
     * @var \phspring\net\server\protocol\IProtocol
     */
    protected $protocol = null;
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
     */
    public function __construct($socketName, array $options = [])
    {
        $this->workerId = spl_object_hash($this);
        Manager::setWorker($this->workerId, $this);
        Manager::setWorkPids($this->workerId, []);

        // Context for socket.
        $this->socketName = $socketName;
        if (!isset($options['socket']['backlog'])) {
            $options['socket']['backlog'] = Macro::DEFAULT_BACKLOG;
        }
        $this->socketContext = stream_context_create($options);
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
     * Get socket name.
     * @return string
     */
    public function getSocketName()
    {
        return $this->socketName ? lcfirst($this->socketName) : 'nobody';
    }

    /**
     * @param int $count
     */
    public function setCount($count)
    {
        $this->count = max(1, (int)$count);
    }

    /**
     * remove a connection
     * @param int $id
     */
    public function removeConnection($id)
    {
        unset($this->connections[$id]);
    }

    /**
     * Listen port.
     * @throws Exception
     */
    abstract public function listen();

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
}
