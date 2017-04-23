<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\helper;

/**
 * Class JsonHelper
 * @package phspring\toolbox\helper
 */
class JsonHelper
{
    /**
     * @param $data
     */
    public static function encode($data)
    {
        return json_encode($data);
    }

    /**
     * @param $data
     */
    public static function decode($data, $assoc = true)
    {
        return json_decode($data, $assoc);
    }
}
