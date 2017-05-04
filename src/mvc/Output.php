<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\mvc;

use phspring\core\Bean;

/**
 * Class Output
 * @package phspring\mvc
 */
class Output extends Bean
{
    /**
     * @param $data
     * @param int $status
     */
    public function send($data, $status = 200)
    {
        $data = Ac::$appContext->packer->encode($data);
    }
}
