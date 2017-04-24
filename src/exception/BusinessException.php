<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\exception;

/**
 * Class BusinessException
 * @package phspring\exception
 */
class BusinessException extends \Exception
{

    /**
     * BusinessException constructor.
     * @param string $message .
     * @param integer $code .
     */
    public function __construct($message, $code = 0)
    {
        parent::__construct($message, $code);
    }

}

