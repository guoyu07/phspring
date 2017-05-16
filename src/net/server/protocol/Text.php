<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server\protocol;

use phspring\net\server\connection\Connection;
use phspring\net\server\connection\Tcp;

/**
 * Class Text
 * @package phspring\net\server\protocol
 */
class Text implements IProtocol
{
    /**
     * Check the integrity of the package.
     * @param string $buffer
     * @param Tcp $connection
     * @return int
     */
    public static function input($buffer, Connection $connection)
    {
        // Judge whether the package length exceeds the limit.
        if (strlen($buffer) >= Tcp::$maxPackageSize) {
            $connection->close();
            return 0;
        }
        //  Find the position of  "\n".
        $pos = strpos($buffer, PHP_EOL);
        // No "\n", packet length is unknown, continue to wait for the data so return 0.
        if ($pos === false) {
            return 0;
        }
        // Return the current package length.
        return $pos + 1;
    }

    /**
     * Encode.
     * @param string $buffer
     * @return string
     */
    public static function encode($buffer, Connection $connection)
    {
        // Add "\n"
        return $buffer . PHP_EOL;
    }

    /**
     * Decode.
     * @param string $buffer
     * @return string
     */
    public static function decode($buffer, Connection $connection)
    {
        // Remove "\n"
        return trim($buffer);
    }
}
