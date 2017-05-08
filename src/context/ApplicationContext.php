<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\context;

use phspring\core\BeanFactory;
use phspring\toolbox\config\Configurator;

/**
 * Class ApplicationContext
 * @package phspring\context
 */
class ApplicationContext extends BeanFactory
{
    /**
     * @var int The global increment id
     */
    public static $globalId = 0;

    /**
     * @var int Process id
     */
    public $pid = 0;
    /**
     * @var \phspring\toolbox\config\Configurator
     */
    public $config = null;
    /**
     * @var \phspring\toolbox\i18n\I18N
     */
    public $i18n = null;
    /**
     * @var \phspring\coroutine\Scheduler
     */
    public $scheduler = null;
    /**
     * @var \phspring\net\pack\IPack
     */
    public $packer = null;
    /**
     * @var \phspring\mvc\route\IRoute
     */
    public $router = null;

    /**
     * ApplicationContext constructor.
     * @param string $configPath
     */
    public function __construct($configPath)
    {
        $this->setConfig($configPath);
        parent::__construct($this->config->get('beans'));
        $this->setPid();
        $this->setI18n();
        $this->setPacker();
        $this->setRouter();
    }

    /**
     * @param $pid
     */
    protected function setPid()
    {
        $this->pid = getmypid();
    }

    /**
     * @param $config
     */
    protected function setConfig($config)
    {
        $this->config = new Configurator($config);
    }

    /**
     * set i18n
     */
    protected function setI18n()
    {
        $this->i18n = $this->getBean('i18n');
    }

    /**
     * set coroutine scheduler.
     */
    protected function setScheduler()
    {
        $this->scheduler = $this->getBean('scheduler');
    }

    /**
     * @return
     */
    protected function setPacker()
    {
        $this->packer = $this->getBean('packer');
    }

    /**
     * @return
     */
    protected function setRouter()
    {
        $this->router = $this->getBean('router');
    }
}
