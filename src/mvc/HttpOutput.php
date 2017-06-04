<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\mvc;

use phspring\context\Ac;
use phspring\net\server\protocol\Http;

/**
 * Class HttpOutput
 * @package phspring\mvc
 */
class HttpOutput extends Output
{
    /**
     * @var array
     */
    protected $headers = [];

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
     * add cookie
     * @param string $name
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httpOnly
     */
    public function addCookie($name, $value, $expire = 0, $path = '/', $domain = '', $secure = false, $httpOnly = false)
    {
        $this->addHeader('Set-Cookie', Http::setcookie($name, $value, $expire, $path, $domain, $secure, $httpOnly));
    }

    /**
     * @param mixed $body
     * @param bool $gzip
     * @param bool $cleanup
     */
    public function end($body = '', $gzip = true, $raw = false)
    {
        if ($gzip) {
            $this->addHeader('Content-Encoding', 'gzip');
            $this->addHeader('Vary', 'Accept-Encoding');
            $body = gzencode($body . " \n", 9);
        }
        if (!is_string($body)) {
            $this->addHeader('Content-Type', 'application/json');
            $body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        if (!isset($this->headers['Connection'])) {
            $this->addHeader('Connection', 'keep-alive');
        }
        $this->addHeader('Content-Length', strlen($body));
        $this->addHeader('Server', 'phspring/' . Ac::$version);
        $header = Http::packHeaders($this->headers);
        $this->connection->close($header . $body, $raw);
    }
}
