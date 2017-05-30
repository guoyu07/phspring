<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\mvc\route;

use phspring\context\Ac;
use phspring\core\Bean;
use phspring\mvc\HttpInput;

/**
 * class IRoute
 * @package phspring\mvc\route
 */
class Route extends Bean implements IRoute
{
    /**
     * @var null route cache.
     */
    protected static $cache = null;

    /**
     * @var string
     */
    private $_moduleName = '';
    /**
     * @var string
     */
    private $_controllerName = '';
    /**
     * @var string
     */
    private $_actionName = '';
    /**
     * @var null
     */
    private $_params = null;

    /**
     * @return string
     */
    public function getModuleName()
    {
        return $this->_moduleName;
    }

    /**
     * @return string
     */
    public function getControllerName()
    {
        return $this->_controllerName;
    }

    /**
     * @return string
     */
    public function getActionName()
    {
        return $this->_actionName;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->_params;
    }

    /**
     * @return
     */
    public function parseRequest($data)
    {
        $this->_moduleName = $data['moduleName'];
        $this->_controllerName = $data['controllerName'];
        $this->_actionName = $data['actionName'];

        return [$this->_controllerName, $this->_actionName, $this->_params];
    }

    /**
     * @param HttpInput $httpInput
     * @return array
     */
    public function parseHttpRequest(HttpInput $httpInput)
    {
        $path = $httpInput->getUri();
        if ($path === '') {
            $path = Ac::config()->get('server.http.defaultPath', '');
        }
        if ($path === '') {
            return false;
        }
        $route = explode('/',  $path);
        if (count($route) < 2) {
            return false;
        }
        $this->_actionName = array_pop($route);
        $this->_controllerName = implode('\\', $route);

        $this->_params = $httpInput->getQueryParams();

        return [$this->_controllerName, $this->_actionName, $this->_params];
    }

    /**
     * @param string $key
     * @param array $val
     */
    public function addCache($key, array $val)
    {
        self::$cache[$key] = $val;
    }

    /**
     * @param $key
     */
    public function delCache($key)
    {
        unset(self::$cache[$key]);
    }
}
