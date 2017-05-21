<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\coroutine;

/**
 * Class Instance
 * @package phspring\coroutine
 */
class Instance
{
    /**
     * @var Instance
     */
    private static $instance;

    /**
     * Instance constructor.
     */
    public function __construct()
    {
        self::$instance = &$this;
    }

    /**
     * @return Instance
     */
    public static function &get()
    {
        if (self::$instance == null) {
            new Instance();
        }

        return self::$instance;
    }
}