<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\context;

use phspring\core\aop\AopFactory;
use phspring\core\Bean;
use phspring\core\IRecoverable;
use phspring\core\memory\Pool;

/**
 * Class Context
 * @package phspring\context
 */
class Context extends Bean implements IRecoverable
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
    public $recoverableBeans = [];

    /**
     * initialize
     */
    public function init()
    {
        $this->uuid = $this->genDistributedId();
        $this->pool = AopFactory::getPool(Ac::getBean('pool'), $this);
    }

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
        foreach ($this->recoverableBeans as $key => $bean) {
            $this->pool->recover($bean);
        }
        $this->recoverableBeans = [];
        $this->pool = null;
    }
}
