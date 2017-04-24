<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\exception;

/**
 * Class InvalidParamsException
 * @package phspring\exception
 */
class InvalidParamsException extends \ErrorException
{

    /**
     * InvalidParamsException constructor.
     * @param string $message .
     * @param integer $code .
     */
    public function __construct($message, $code)
    {
        parent::__construct($message, $code);
    }

}

