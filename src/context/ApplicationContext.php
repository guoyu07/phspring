<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\context;

use phspring\core\BeanFactory;
use phspring\coroutine\Scheduler;
use phspring\mvc\route\IRoute;
use phspring\mvc\route\Route;
use phspring\net\pack\IPack;
use phspring\net\pack\JsonPack;
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
        parent::__construct($this->config->get('beans', []));
        $this->setPid();
        $this->setI18n();
        $this->setPacker();
        $this->setRouter();
    }

    /**
     * @param $pid
     */
    public function setPid()
    {
        $this->pid = getmypid();
    }

    /**
     * @param $config
     */
    public function setConfig($config)
    {
        $this->config = new Configurator($config);
    }

    /**
     * set i18n
     */
    public function setI18n()
    {
        $i18nConfig = $this->config->get('i18n');
        if (!empty($i18nConfig)) {
            $this->i18n = $this->getBean('i18n', $i18nConfig);
        }
    }

    /**
     * set coroutine scheduler.
     */
    public function setScheduler()
    {
        $this->scheduler = new Scheduler();
    }

    /**
     * @param IPack $packer
     */
    public function setPacker(IPack $packer = null)
    {
        if ($packer === null) {
            $packer = $this->config->get('server.packer', JsonPack::class);
        }
        $this->packer = new $packer();
    }

    /**
     * @param IRoute|null $router
     */
    public function setRouter(IRoute $router = null)
    {
        if ($router === null) {
            $router = $this->config->get('server.router', Route::class);
        }
        $this->router = new $router();
    }
}
