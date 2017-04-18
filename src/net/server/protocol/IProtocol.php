<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server\protocol;

use phspring\net\server\connection\Connection;

/**
 * Interface IProtocol
 * @package phspring\net\server\protocol
 */
interface IProtocol
{

    /**
     * @param ConnectionInterface $connection
     * @param string $recvBuffer
     * @return int|false
     */
    public static function input($recvBuffer, Connection $connection);

    /**
     * @param Connection $connection
     * @param string $recvBuffer
     * @return mixed
     */
    public static function decode($recvBuffer, Connection $connection);

    /**
     * @param Connection $connection
     * @param mixed $data
     * @return string
     */
    public static function encode($data, Connection $connection);
}
