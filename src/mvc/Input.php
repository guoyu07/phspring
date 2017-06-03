<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\mvc;

use phspring\core\IReusable;
use phspring\core\PoolBean;

/**
 * Class Input
 * @package phspring\mvc
 */
class Input extends PoolBean implements IReusable
{
    /**
     * @var string http|tcp
     */
    public $reqType = 'http';

    /**
     * bean pool clear
     * @return
     */
    public function cleanup()
    {

    }
}
