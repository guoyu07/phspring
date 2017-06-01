<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\mvc;

use phspring\core\Bean;
use phspring\core\IReusable;

/**
 * Class Output
 * @package phspring\mvc
 */
class Output extends Bean implements IReusable
{
    /**
     * @param $data
     * @param int $status
     */
    public function send($data, $status = 200)
    {
        $data = Ac::$appContext->packer->encode($data);
    }

    /**
     * bean pool clear
     * @return
     */
    public function cleanup()
    {

    }
}
