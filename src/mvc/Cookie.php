<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\mvc;

use phspring\core\Bean;

/**
 * Class Cookie
 * @package phspring\mvc
 */
class Cookie extends Bean
{
    /**
     * @var
     */
    public $name;
    /**
     * @var
     */
    public $value;
    /**
     * @var
     */
    public $domain;
    /**
     * @var int
     */
    public $expire = 0;
    /**
     * @var
     */
    public $path;

    /**
     * @param $name
     */
    public function get($name)
    {

    }

    /**
     * @param $name
     * @param $value
     */
    public function add($name, $value)
    {

    }

    /**
     * @param $name
     */
    public function contain($name)
    {

    }

    /**
     * @return bool
     */
    public function remove()
    {

    }
}

