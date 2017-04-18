<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\exception;

/**
 * Class CoroutineException
 * @package phspring\exception
 */
class CoroutineException extends SwooleException
{
    /**
     * @return string
     */
    public function getPreviousMessage()
    {
        return $this->getPrevious()->getMessage();
    }

    /**
     * CoroutineException constructor.
     * @param string $message
     * @param int $code
     * @param \Exception|null $previous
     */
    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
