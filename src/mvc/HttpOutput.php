<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\mvc;

use phspring\context\Ac;

/**
 * Class HttpOutput
 * @package phspring\mvc
 */
class HttpOutput extends Output
{
    /**
     * @var string
     */
    public $layout = 'main';

    /**
     * @var array
     */
    protected $cookies;
    /**
     * @var array
     */
    protected $headers;

    /**
     * @var View
     */
    private $_view;

    /**
     * add cookie
     * @param string $name
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     */
    public function addCookie($name, $value, $expire = 0, $path = '/', $domain = '', $secure = false)
    {
        $this->cookies[] = [$name, $value, $expire, $path, $domain, $secure];
    }

    /**
     * add header
     * @param $name
     * @param $value
     */
    public function addHeader($name, $value)
    {
        $this->headers[$name][] = $value;
    }

    /**
     * get view
     */
    public function setView($view)
    {
        $this->_view = $view;
    }

    /**
     * render html page.
     * @param $view
     * @param array $params
     * @param bool $partial
     * @return mixed
     */
    public function render($view, $params = [], $partial = false)
    {
        $content = $this->_view->render($view, $params, $partial);
        return $content;
    }

    /**
     * @param $data
     * @param int $status
     * @return mixed
     */
    public function send($data, $status = 200)
    {
        $this->sendHeaders();
        $data = Ac::$appContext->packer->encode($data);
        // ...
    }

    /**
     * send headers
     */
    protected function sendHeaders()
    {
        if (empty($this->headers)) {
            return;
        }
        if (!headers_sent()) {
            foreach ($this->headers as $name => $values) {
                $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
                $replace = true;
                foreach ($values as $value) {
                    header("$name: $value", $replace);
                    $replace = false;
                }
            }
        }
        $this->sendCookies();
    }

    /**
     * send cookies
     */
    protected function sendCookies()
    {
        if (empty($this->cookies)) {
            return;
        }
        foreach ($this->cookies as $cookie) {
            setcookie($cookie[0], $cookie[1], $cookie[2], $cookie[3], $cookie[4], $cookie[5]);
        }
    }
}
