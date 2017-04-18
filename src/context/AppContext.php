<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\context;

use phspring\core\BeanFactory;
use phspring\toolbox\config\Configurator;

/**
 * Class AppContext
 * @package phspring\context
 */
class AppContext extends BeanFactory
{
    /**
     * @var \phspring\toolbox\config\Configurator
     */
    public $config = null;
    /**
     * @var \phspring\toolbox\i18n\I18N
     */
    public $i18n = null;

    /**
     * AppContext constructor.
     * @param string $configPath
     */
    public function __construct($configPath)
    {
        $this->setConfig($configPath);
        $this->setI18n();
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
        $i18nConfig = $this->config->get(i18n);
        if (!empty($i18nConfig)) {
            $this->i18n = $this->getBean('i18n', $i18nConfig);
        }
    }

}
