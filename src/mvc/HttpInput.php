<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\mvc;

/**
 * Class HttpInput
 * @package phspring\mvc
 */
class HttpInput extends Input
{
    /**
     * @var array
     */
    public $get = [];
    /**
     * @var array
     */
    public $post = [];
    /**
     * @var array
     */
    public $server = [];
    /**
     * @var array
     */
    public $cookie = [];
    /**
     * @var array
     */
    public $files = [];

    /**
     * @param array $data
     */
    public function init(array $data)
    {
        $this->get = $data['get'];
        $this->post = $data['post'];
        $this->server = $data['server'];
        $this->cookie = $data['cookie'];
        $this->files = $data['files'];
    }

    /**
     * @return bool
     */
    public function isGet(): bool
    {

    }

    /**
     * @return bool
     */
    public function isPost(): bool
    {

    }

    /**
     * @return bool
     */
    public function isOptions(): bool
    {

    }

    /**
     * @return bool
     */
    public function isHead(): bool
    {

    }

    /**
     * @return bool
     */
    public function isDelete(): bool
    {

    }

    /**
     * @return bool
     */
    public function isPut(): bool
    {

    }

    /**
     * @return bool
     */
    public function isPatch(): bool
    {

    }

    /**
     * @return bool
     */
    public function isAjax(): bool
    {

    }

    /**
     * @return string
     */
    public function getProtocol()
    {

    }

    /**
     * @return string
     */
    public function getUri()
    {

    }

    /**
     * @return string
     */
    public function getScheme()
    {

    }

    /**
     * @return int
     */
    public function getPort()
    {

    }

    /**
     * @return string
     */
    public function getIp()
    {

    }

    /**
     * @return string
     */
    public function getAction()
    {
        if (isset($_POST[$this->requestAction])) {
            return strtoupper($_POST[$this->requestAction]);
        }
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            return strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }
        if (isset($_SERVER['REQUEST_METHOD'])) {
            return strtoupper($_SERVER['REQUEST_METHOD']);
        }

        return 'GET';
    }

    /**
     * @return string
     */
    public function getReferer()
    {

    }

    /**
     * @return string
     */
    public function getRawBody()
    {
        return file_get_contents('php://input');
    }

    /**
     * @return string
     */
    public function getQueryParams()
    {

    }

    /**
     * @param $name
     * @param null $default
     */
    public function getBodyParams($name, $default = null)
    {

    }

    /**
     * @param $name
     * @param null $default
     */
    public function post($name, $default = null)
    {

    }

    /**
     * @param $name
     * @param $default
     */
    public function get($name, $default)
    {

    }
}
