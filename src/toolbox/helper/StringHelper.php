<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\helper;

/**
 * Class StringHelper
 * @package phspring\toolbox\helper
 */
class StringHelper
{
    /**
     * safe base64UrlEncode
     */
    public static function safeBase64Encode($data)
    {
        return strtr(base64_encode($data), '+/', '-_');
    }

    /**
     * @param $input
     * @return string
     */
    public static function safeBase64Decode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
