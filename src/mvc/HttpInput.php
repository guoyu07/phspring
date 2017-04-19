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
    public function isGet(): bool
    {

    }

    public function isPost(): bool
    {

    }

    public function isOptions(): bool
    {

    }

    public function isHead(): bool
    {

    }

    public function isDelete(): bool
    {

    }

    public function isPut(): bool
    {

    }

    public function isPatch(): bool
    {

    }

    public function isAjax(): bool
    {

    }

    public function getProtocol()
    {

    }

    public function getUri()
    {

    }

    public function getScheme()
    {

    }

    public function getPort()
    {

    }

    public function getIp()
    {

    }

    public function getMethod()
    {
        if (isset($_POST[$this->requestMethod])) {
            return strtoupper($_POST[$this->requestMethod]);
        }
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            return strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }
        if (isset($_SERVER['REQUEST_METHOD'])) {
            return strtoupper($_SERVER['REQUEST_METHOD']);
        }

        return 'GET';
    }

    public function getReferer()
    {

    }

    public function getRawBody()
    {
        return file_get_contents('php://input');
    }

    public function getQueryParams()
    {

    }

    public function getBodyParams($name, $default = null)
    {

    }

    public function post($name, $default = null)
    {

    }

    public function get($name, $default)
    {

    }
}
