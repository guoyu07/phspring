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
}
