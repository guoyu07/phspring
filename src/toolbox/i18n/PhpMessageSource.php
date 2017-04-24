<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\i18n;

/**
 * Class PhpMessageSource
 * @package phspring\toolbox\i18n
 */
class PhpMessageSource extends MessageSource
{
    /**
     * @var string the base path for all translated messages. Defaults to '<DIR>/messages'.
     */
    public $basePath = '';
    /**
     * @var array mapping between message categories and the corresponding message file paths.
     * The file paths are relative to [[basePath]]. For example,
     *
     * ~~~
     * [
     *     'core' => 'core.php',
     *     'ext' => 'extensions.php',
     * ]
     * ~~~
     */
    public $fileMap;

    /**
     * 加载语言
     *
     * @param string $category 分类 eg app.errno
     * @param string $language 语言
     * @return array|mixed|null
     */
    protected function loadMessages($category, $language)
    {
        $messageFile = $this->getMessageFilePath($category, $language);
        $messages = $this->loadMessagesFromFile($messageFile);

        $fallbackLanguage = substr($language, 0, 2);
        if ($fallbackLanguage !== $language) {
            $fallbackMessageFile = $this->getMessageFilePath($category, $fallbackLanguage);
            $fallbackMessages = $this->loadMessagesFromFile($fallbackMessageFile);

            if ($messages === null && $fallbackMessages === null && $fallbackLanguage !== $this->sourceLanguage) {
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
    protected function getMessageFilePath($category, $language)
    {
        $suffix = explode('.', $category)[1];
        $messageFile = $this->basePath . "/$language/";
        if (isset($this->fileMap[$suffix])) {
            $messageFile .= $this->fileMap[$suffix];
        } else {
            $messageFile .= str_replace('\\', '/', $suffix) . '.php';
        }

        return $messageFile;
    }

    /**
     * 从翻译配置文件中加载
     *
     * @param $messageFile
     * @return array|mixed|null
     */
    protected function loadMessagesFromFile($messageFile)
    {
        if (is_file($messageFile)) {
            $messages = include($messageFile);
            if (!is_array($messages)) {
                $messages = [];
            }

            return $messages;
        } else {
            return null;
        }
    }
}
