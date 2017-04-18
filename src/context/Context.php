<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\context;

/**
 * Class Context
 * @package phspring\context
 */
class Context extends AppContext
{
    /**
     * @var null
     */
    public $input = null;
    /**
     * @var null
     */
    public $output = null;
    /**
     * @var
     */
    public $log = null;
}
