<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server;

use phspring\net\server\protocol\Http;

/**
 * Class HttpServer
 * @package phspring\net\server
 */
class HttpServer extends Worker
{
    /**
     * HttpServer constructor.
     * @param string $socketName
     * @param array $options
     */
    public function __construct($socketName, array $options = [])
    {
        if (strpos($socketName, 'http') !== 0) {
            echo 'Not http protocol' . PHP_EOL;
            exit(250);
        }
        parent::__construct($socketName, $options);
        //$this->name = 'HttpServer';
    }

    /**
     * run
     */
    public function run()
    {
        $this->onWorkerStart = $this->onWorkerStart;
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
