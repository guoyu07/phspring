<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\context;

use phspring\beans\log\Log;
use phspring\core\aop\AopFactory;
use phspring\core\Bean;
use phspring\core\IReusable;
use phspring\core\memory\Pool;
use phspring\mvc\Input;
use phspring\mvc\Output;

/**
 * Class Context
 * @package phspring\context
 */
class Context extends Bean implements IReusable
{
    /**
     * @var string unique request id
     */
    public $uuid = '';
    /**
     * @var string log trace id
     */
    public $logTraceId = '';
    /**
     * @var null
     */
    public $input = null;
    /**
     * @var null
     */
    public $output = null;
    /**
     * @var \phspring\toolbox\log\Log
     */
    public $log = null;
    /**
     * Object pool
     * @var Pool
     */
    public $pool = null;
    /**
     * use to flag bean pool recover, that will auto recover bean to pool.
     * @var array
     */
    public $reusableBeans = [];

    /**
     * initialize
     */
    public function init()
    {
        $this->uuid = $this->genDistributedId();
        $this->pool = AopFactory::getPool(Ac::getBean('pool'), $this);
    }

    /**
     * @param Input $input
     */
    public function setInput(Input $input)
    {
        $this->input = $input;
    }

    /**
     * @param Output $output
     */
    public function setOutput(Output $output)
    {
        $this->output = $output;
    }

    /**
     * @param Log $log
     */
    public function setLog(Log $log)
    {
        $this->log = $log;
    }

    /**
     * gen a context uuid with 24 length.
     */
    public function genDistributedId()
    {
        $time = time() & 0xFFFFFFFF;
        $machine = crc32(substr((string)gethostname(), 0, 256)) >> 8 & 0xFFFFFF;
        $process = Ac::$appContext->pid & 0xFFFF;
        $id = ApplicationContext::$globalId = ApplicationContext::$globalId > 0xFFFFFE ? 1 : ApplicationContext::$globalId + 1;

        return sprintf('%08x%06x%04x%06x', $time, $machine, $process, $id);
    }

    /**
     * bean pool clear
     * @return
     */
    public function cleanup()
    {
        $this->uuid = '';
        $this->logTraceId = '';
        $this->log = null;
        $this->input = null;
        $this->output = null;
        foreach ($this->reusableBeans as $key => $bean) {
            $this->pool->push($bean);
        }
        $this->reusableBeans = [];
        $this->pool = null;
    }
}
