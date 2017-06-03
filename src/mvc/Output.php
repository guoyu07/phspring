<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\mvc;

use phspring\core\IReusable;
use phspring\core\PoolBean;

/**
 * Class Output
 * @package phspring\mvc
 */
class Output extends PoolBean implements IReusable
{
    /**
     * @param $data
     * @param int $status
     */
    public function send($data, $status = 200)
    {
        if (!is_string($data)) {
            $data = Ac::$appContext->packer->encode($data);
        }
    }

    /**
     * bean pool clear
     * @return
     */
    public function cleanup()
    {

    }
}
