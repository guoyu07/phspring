<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\exception;

/**
 * Class NetException
 * @package phspring\exception
 */
class NetException extends \Exception
{
    /**
     * NetException constructor.
     * @param string $message
     * @param int $code
     * @param \Exception|null $previous
     */
    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * append info.
     * @param $others
     * @param BaseController $controller
     */
    public function append($infos, $controller = null)
    {
        //  BusinessException 不打印在终端.
        if ($this->getPrevious() instanceof BusinessException) {
            return;
        }
        if (!empty($infos)) {
            print_r($infos . "\n");
        } else {
            print_r($this->getMessage() . "\n");
            print_r($this->getTraceAsString() . "\n");
        }
        print_r("\n");
    }
}
