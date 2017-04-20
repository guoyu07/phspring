<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\coroutine;

/**
 * Class Null
 * @package phspring\coroutine
 */
class Null
{
    /**
     * @var Null
     */
    private static $instance;

    /**
     * Null constructor.
     */
    public function __construct()
    {
        self::$instance = &$this;
    }

    /**
     * @return Null|Null
     */
    public static function &getInstance()
    {
        if (self::$instance == null) {
            new Null();
        }
        return self::$instance;
    }
}