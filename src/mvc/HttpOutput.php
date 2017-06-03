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
     * @param mixed $content
     * @param bool $gzip
     * @param bool $cleanup
     */
    public function end($content = '', $gzip = true)
    {
        $encoding = strtolower($this->request->header['accept-encoding'] ?? '');
        if ($gzip && strpos($encoding, 'gzip') !== false) {
            $this->response->gzip(1);
        }
        if (!is_string($content)) {
            $this->setHeader('Content-Type', 'application/json');
            $content = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        $this->response->end($content);
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
