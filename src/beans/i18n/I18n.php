<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\beans\i18n;

use phspring\context\Ac;
use phspring\core\Bean;
use phspring\core\BeanFactory;

/**
 * Class I18n
 * @package phspring\beans\i18n
 */
class I18n extends Bean
{
    /**
     * @var string
     */
    public $scope = BeanFactory::SCOPE_SINGLETON;
    /**
     * @var array
     */
    public $translations = [];

    /**
     * @var string|array|Formatter
     */
    private $_formatter;

    /**
     * Initializes the component by configuring the default message categories.
     * @param array $config 配置，如：
     * [
     *     'app' => [
     *         'class' => 'PhpSource', <选填，默认为 PhpSource>
     *         'sourceLang' => 'en_us', <必填>
     *         'basePath' => '<DIR>/lang', // 翻译配置文件路径 <必填>
     *         'fileMap' => [ <必填>
     *             'common' => 'common.php',
     *             'error' => 'error.php'
     *         ]
     *     ],
     *     'other' => [
     *         'class' => 'PhpSource', <选填>
     *         'sourceLang' => 'en_us', <必填>
     *         'basePath' => '<DIR>/other/lang', // 翻译配置文件路径 <必填>
     *         'fileMap' => [ <必填>
     *             'a' => 'a.php',
     *             'b' => 'b.php'
     *         ]
     *     ]
     * ]
     */
    public function init()
    {

    }

    /**
     * Mutil language translate, the usage eg.
     * 1) Ac::trans('common', 'hot', [], 'zh-CN'); // default app.common
     * 2) Ac::trans('app.common', 'hot', [], 'zh-CN'); // like 1)
     * 3) Ac::trans('msg.a', 'hello', ['{foo}' => 'bar', '{key}' => 'val'], 'ja-JP');
     * @param string $category
     * @param string $message
     * @param array $params
     * @param null | string $language
     * @return mixed
     */
    public function translate($category, $message, $params = [], $language = null)
    {
        if (strpos($category, '.') === false) {
            $category = 'app.' . $category;
        }
        $source = $this->getSource($category);
        $translation = $source->translate($category, $message, $language);
        if ($translation === false) {
            return $this->format($message, $params, $source->sourceLang);
        } else {
            return $this->format($translation, $params, $language);
        }
    }

    /**
     * Formats a message using [[Formatter]].
     *
     * @param string $message the message to be formatted.
     * @param array $params the parameters that will be used to replace the corresponding placeholders in the message.
     * @param string $language the language code (e.g. `en-US`, `en`).
     * @return string the formatted message.
     */
    public function format($message, $params, $language)
    {
        $params = (array)$params;
        if ($params === []) {
            return $message;
        }

        if (preg_match('~{\s*[\d\w]+\s*,~u', $message)) {
            /* @var $formatter Formatter */
            $formatter = $this->getFormatter();
            $result = $formatter->format($message, $params, $language);
            if ($result === false) {
                // $errorMessage = $formatter->getErrorMessage();
                return $message;
            } else {
                return $result;
            }
        }

        $p = [];
        foreach ($params as $name => $value) {
            $p['{' . $name . '}'] = $value;
        }

        return strtr($message, $p);
    }

    /**
     * Returns the message formatter instance.
     * @return Formatter the message formatter to be used to format message via ICU message format.
     */
    public function getFormatter()
    {
        if ($this->_formatter === null) {
            $this->_formatter = new Formatter();
        } elseif (is_array($this->_formatter) || is_string($this->_formatter)) {
            $this->_formatter = Ac::getBean($this->_formatter);
        }

        return $this->_formatter;
    }

    /**
     * @param string|array|Formatter $value the message formatter to be used to format message via ICU message format.
     * Can be given as array or string configuration that will be given to [[Ac::getBean]] to create an instance
     * or a [[Formatter]] instance.
     */
    public function setFormatter($value)
    {
        $this->_formatter = $value;
    }

    /**
     * Returns the message source for the given category.
     * @param string $category the category name, eg. app.errno
     * @return Source the message source for the given category.
     * @throws InvalidConfigException if there is no message source available for the specified category.
     */
    public function getSource($category)
    {
        $prefix = explode('.', $category)[0];
        if (isset($this->translations[$prefix])) {
            $source = $this->translations[$prefix];
            if ($source instanceof Source) {
                return $source;
            } else {
                return $this->translations[$prefix] = Ac::getBean($source);
            }
        }

        throw new \Exception("Unable to locate message source for category '$category'.");
    }
}
