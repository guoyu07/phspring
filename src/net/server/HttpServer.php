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
class HttpServer extends Server
{
    /**
     * HttpServer constructor.
     * @param string $socketName
     * @param array $contextOption
     */
    public function __construct($socketName, array $contextOption = [])
    {
        parent::__construct($socketName, $contextOption);
    }
}
