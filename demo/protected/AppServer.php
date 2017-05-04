<?php
/**
 * This file is part of the phspring package.
 */
namespace app;

use phspring\net\server\HttpServer;

/**
 * Class AppServer
 * @package app
 */
class AppServer extends HttpServer
{

    /**
     * AppServer constructor.
     * @param $config
     */
    public function __construct($config)
    {
        parent::__construct();
    }
    
    /**
     * run server
     */
    public function run()
    {

    }
}
