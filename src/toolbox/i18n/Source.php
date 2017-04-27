<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\i18n;

use phspring\core\Bean;

/**
 * Class Source
 * @package phspring\toolbox\i18n
 */
class Source extends Bean
{
    /**
     * @var boolean whether to force message translation when the source and target languages are the same.
     * Defaults to false, meaning translation is only performed when source and target languages are different.
     */
    public $forceTranslation = false;
    /**
     * @var string the language that the original messages are in. If not set, it will use the value of
     */
    public $sourceLanguage = 'en-US';

    /**
     * @var array
     */
    private $_messages = [];

    /**
     * 加载语言
     *
     * @param string $category 分类
     * @param string $language 语言
     * @return array
     */
    protected function load($category, $language)
    {
        return [];
    }

    /**
     * 执行翻译
     *
     * @param string $category 分类
     * @param string $message 要翻译的信息
     * @param string $language 要翻译成的语言
     * @return bool|string
     */
    public function translate($category, $message, $language)
    {
        if ($this->forceTranslation || $language !== $this->sourceLanguage) {
            return $this->doTranslate($category, $message, $language);
        } else {
            return false;
        }
    }

    /**
     * 执行翻译
     *
     * @param string $category 分类
     * @param string $message 要翻译的信息
     * @param string $language 要翻译成的语言
     * @return bool|string
     */
    protected function doTranslate($category, $message, $language)
    {
        $cates = explode('.', $category);
        $key = $cates[0] . '/' . $language . '/' . $cates[1]; // eg: app/en-US/errno
        if (!isset($this->_messages[$key])) {
            $this->_messages[$key] = $this->load($category, $language);
        }
        if (isset($this->_messages[$key][$message]) && $this->_messages[$key][$message] !== '') {
            return $this->_messages[$key][$message];
        } else {
            // ...
        }

        return $this->_messages[$key][$message] = false;
    }
}
