<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\pack;

use phspring\toolbox\helper\JsonHelper;

/**
 * Class JsonPack
 * @package phspring\net\pack
 */
class JsonPack implements IPack
{
    /**
     * @param $data
     * @return string
     */
    public function encode($data)
    {
        return JsonHelper::encode($data);
    }

    /**
     * @param $data
     * @return mixed
     */
    public function decode($data)
    {
        return JsonHelper::decode($data);
    }
}
