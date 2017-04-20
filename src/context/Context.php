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
     * @var null logger
     */
    public $log = null;
    /**
     * @var string log trace id
     */
    public $logTraceId = '';
    /**
     * @var string unique request id
     */
    public $uuid = '';

    /**
     * @param $input \phspring\mvc\Input
     */
    public function setInput($input)
    {
        $this->input = $input;
    }

    /**
     * @param $output \phspring\mvc\Output
     */
    public function setOutput($output)
    {
        $this->output = $output;
    }

    /**
     * @param $log
     */
    public function setLog($log)
    {
        $this->log = $log;
    }

    /**
     * gen context uuid
     */
    public function genUuid()
    {
        $this->uuid = '';
    }
}
