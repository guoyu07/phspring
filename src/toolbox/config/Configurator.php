<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\config;

use phspring\exception\InvalidConfigException;

/**
 * Class Config
 * @package phspring\toolbox\config
 */
class Configurator
{
    /**
     * The default key separator.
     */
    const SEPARATOR = '.';

    /**
     * @var array
     */
    private $_data = null;
    /**
     * @var array
     */
    private $_cache = null;

    /**
     * Config constructor.
     * @param string|array $config
     * @throws \Exception | InvalidConfigException
     */
    public function __construct($config)
    {
        if (is_string($config)) {
            if (!file_exists($config)) {
                throw new \Exception('Config file not found.');
            }
            $content = include_once($config);
            if (!is_array($content)) {
                throw new InvalidConfigException('Config format invalid.');
            }
            $this->_data = $content;
        } elseif (is_array($config)) {
            $this->_data = $config;
        } else {
            throw new InvalidConfigException('Config invalid.');
        }
    }

    /**
     * @param $key
     * @param $default
     * @return mixed
     */
    public function get(String $key, $default = null)
    {
        if ($this->contain($key)) {
            return $this->_cache[$key];
        }

        return $default;
    }

    /**
     * @param string $key
     * @param mixed $val
     * @throws \Exception
     */
    public function set(String $key, $val)
    {
        $cKey = '';
        $root = &$this->_data;
        $segs = $this->parseKey($key);

        while ($child = array_shift($segs)) {
            if ($cKey != '') {
                $cKey .= self::SEPARATOR;
            }
            $cKey .= $child;
            if (!isset($root[$child]) && count($segs) > 0) {
                $root[$child] = [];
            }
            if (!is_array($root)) {
                throw new \Exception('Can not set ' . $key);
            }
            $root = &$root[$child];

            unset($this->_cache[$cKey]);

            // unset outdated cache data.
            if (count($segs) == 0) {
                foreach ($this->_cache as $k => $v) {
                    if (substr($k, 0, strlen($cKey)) === $cKey) {
                        unset($this->_cache[$k]);
                    }
                }
            }
        }

        $this->_cache[$key] = $root = $val;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function contain(String $key)
    {
        if (isset($this->_cache[$key])) {
            return true;
        }

        $segs = $this->parseKey($key);
        $root = $this->_data;
        foreach ($segs as $seg) {
            if (is_array($root) && array_key_exists($seg, $root)) {
                $root = $root[$seg];
                continue;
            } else {
                return false;
            }
        }
        $this->_cache[$key] = $root;

        return true;
    }

    /**
     * @param string $key
     */
    public function empty(String $key)
    {
        return empty($this->get($key));
    }

    /**
     * @param String $key
     */
    private function parseKey(String $key)
    {
        return explode(self::SEPARATOR, $key);
    }
}
