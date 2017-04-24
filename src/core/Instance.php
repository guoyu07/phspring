<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\core;

/**
 * Class Instance
 * @package phspring\core
 */
class Instance
{
    /**
     * @var string
     */
    public $name;

    /**
     * Instance constructor.
     * @param $id
     */
    protected function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @param $name
     * @return static
     */
    public static function of($name)
    {
        return new static($name);
    }
}
