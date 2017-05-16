<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server;

/**
 * Class TcpServer
 * @package phspring\net\server
 */
class TcpServer extends Worker
{
    /**
     * TcpServer constructor.
     * @param string $socketName
     * @param array $options
     */
    public function __construct($socketName, array $options = [])
    {
        parent::__construct($socketName, $options);
    }

    /**
     * run
     */
    public function run()
    {
        $this->onWorkerStart = [$this, 'onWorkerStart'];
        $this->onMessage = [$this, 'onMessage'];
        parent::run();
    }

    /**
     * on worker start
     */
    public function onWorkerStart()
    {
        echo 'worker start';
    }

    /**
     * on message
     */
    public function onMessage($connection)
    {
        echo 'on message';
    }
}
