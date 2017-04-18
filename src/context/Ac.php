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
     * @var AppContext
     */
    public static $appContext = null;

    /**
     * @param AppContext $ac
     */
    public static function setAppContext(AppContext $appContext)
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
    public static function getBean($name, array $params = [])
    {
        return self::$appContext->getBean($name, $params);
    }

    /**
     * @param $category
     * @param $message
     * @param $params
     * @param $language
     * @return mixed
     */
    public static function i18n($category, $message, $params, $language)
    {
        return self::$appContext->i18n->translate($category, $message, $params, $language);
    }
}
