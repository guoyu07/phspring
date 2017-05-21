<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\context;

/**
 * Class Ac
 * @package phspring\context
 */
class Ac
{
    /**
     * @var string
     */
    public static $version = '0.9';
    /**
     * @var ApplicationContext
     */
    public static $appContext = null;

    /**
     * @param ApplicationContext $ac
     */
    public static function setApplicationContext(ApplicationContext $appContext)
    {
        self::$appContext = $appContext;
    }

    /**
     * Ac::config->get('foo', 'bar');
     * Ac::config->set('foo', 'bar');
     * Ac::config->contain('foo');
     * Ac::config->empty('foo');
     * @return \phspring\toolbox\config\Configurator
     */
    public static function config()
    {
        return self::$appContext->config;
    }

    /**
     * @param string $name
     * @param array $params
     * @return mixed
     */
    public static function getBean($name, Context $context = null, array $args = [], $definition)
    {
        return self::$appContext->getBean($name, $context, $args, $definition);
    }

    /**
     * translate message
     * @param $category
     * @param $message
     * @param $params
     * @param $language
     * @return mixed
     */
    public static function trans($category, $message, $params, $language = 'en-US')
    {
        return self::$appContext->i18n->translate($category, $message, $params, $language);
    }
}
