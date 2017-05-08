<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\i18n;

/**
 * Class PhpSource
 * @package phspring\toolbox\i18n
 */
class PhpSource extends Source
{
    /**
     * @var string the base path for all translated messages. Defaults to '<DIR>/messages'.
     */
    public $basePath = '';
    /**
     * @var array mapping between message categories and the corresponding message file paths.
     * The file paths are relative to [[basePath]]. For example,
     * [
     *     'common' => 'common.php',
     *     'other' => 'others.php',
     * ]
     */
    public $fileMap;

    /**
     * load messages.
     *
     * @param string $category category eg app.errno
     * @param string $language language
     * @return array|mixed|null
     */
    protected function load($category, $language)
    {
        $file = $this->getFilePath($category, $language);
        $messages = $this->loadFromFile($file);

        $fallbackLanguage = substr($language, 0, 2);
        if ($fallbackLanguage !== $language) {
            $fallbackFile = $this->getFilePath($category, $fallbackLanguage);
            $fallbackMessages = $this->loadFromFile($fallbackFile);

            if ($messages === null && $fallbackMessages === null && $fallbackLanguage !== $this->sourceLang) {
                // ...
            } elseif (empty($messages)) {
                return $fallbackMessages;
            } elseif (!empty($fallbackMessages)) {
                foreach ($fallbackMessages as $key => $value) {
                    if (!empty($value) && empty($messages[$key])) {
                        $messages[$key] = $fallbackMessages[$key];
                    }
                }
            }
        } else {
            if ($messages === null) {
                // ...
            }
        }

        return (array)$messages;
    }

    /**
     * 获取文件路径
     *
     * @param string $category 分类
     * @param string $language 语言
     * @return string
     */
    protected function getFilePath($category, $language)
    {
        $suffix = explode('.', $category)[1];
        $file = $this->basePath . "/$language/";
        if (isset($this->fileMap[$suffix])) {
            $file .= $this->fileMap[$suffix];
        } else {
            $file .= str_replace('\\', '/', $suffix) . '.php';
        }

        return $file;
    }

    /**
     * 从翻译配置文件中加载
     *
     * @param $file
     * @return array|mixed|null
     */
    protected function loadFromFile($file)
    {
        if (is_file($file)) {
            $messages = include($file);
            if (!is_array($messages)) {
                $messages = [];
            }

            return $messages;
        } else {
            return null;
        }
    }
}
