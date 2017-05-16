<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server\protocol;

use phspring\context\Ac;
use phspring\net\server\connection\Connection;
use phspring\net\server\connection\Tcp;

/**
 * Class Http
 * @package phspring\net\server\protocol
 */
class Http implements IProtocol
{
    /**
     * http eof
     */
    const HTTP_EOF = "\r\n";
    /**
     * double http eof
     */
    const HTTP_EOF_DOUBLE = "\r\n\r\n";

    /**
     * The supported HTTP methods
     * @var array
     */
    public static $methods = [
        'GET',
        'POST',
        'PUT',
        'DELETE',
        'HEAD',
        'OPTIONS'
    ];

    /**
     * Check the integrity of the package.
     *
     * @param string $recvBuffer
     * @param Connection $connection
     * @return int
     */
    public static function input($recvBuffer, Connection $connection)
    {
        if (!strpos($recvBuffer, self::HTTP_EOF_DOUBLE)) {
            // Judge whether the package length exceeds the limit.
            if (strlen($recvBuffer) >= Tcp::$maxPackageSize) {
                $connection->close();
                return 0;
            }
            return 0;
        }

        list($header,) = explode(self::HTTP_EOF_DOUBLE, $recvBuffer, 2);
        $method = substr($header, 0, strpos($header, ' '));

        if (in_array($method, static::$methods)) {
            return static::getRequestSize($header, $method);
        } else {
            $connection->send("HTTP/1.1 400 Bad Request" . self::HTTP_EOF_DOUBLE, true);
            return 0;
        }
    }

    /**
     * Get whole size of the request
     * includes the request headers and request body.
     * @param string $header The request headers
     * @param string $method The request method
     * @return integer
     */
    protected static function getRequestSize($header, $method)
    {
        if ($method == 'GET') {
            return strlen($header) + 4;
        }
        $match = [];
        if (preg_match("/\r\nContent-Length: ?(\d+)/i", $header, $match)) {
            $len = isset($match[1]) ? $match[1] : 0;
            return $len + strlen($header) + 4;
        }

        return 0;
    }

    /**
     * Parse $_POST、$_GET、$_COOKIE.
     * @param string $recvBuffer
     * @param Tcp $connection
     * @return array
     */
    public static function decode($recvBuffer, Connection $connection)
    {
        // Init.
        $_POST = $_GET = $_COOKIE = $_REQUEST = $_SESSION = $_FILES = [];
        $GLOBALS['HTTP_RAW_POST_DATA'] = '';
        // Clear cache.
        HttpCache::$header = [
            'Connection' => 'Connection: keep-alive'
        ];
        HttpCache::$instance = new HttpCache();
        // $_SERVER
        $_SERVER = [
            'QUERY_STRING' => '',
            'REQUEST_METHOD' => '',
            'REQUEST_URI' => '',
            'SERVER_PROTOCOL' => '',
            'SERVER_SOFTWARE' => 'phspring/' . Ac::$version,
            'SERVER_NAME' => '',
            'HTTP_HOST' => '',
            'HTTP_USER_AGENT' => '',
            'HTTP_ACCEPT' => '',
            'HTTP_ACCEPT_LANGUAGE' => '',
            'HTTP_ACCEPT_ENCODING' => '',
            'HTTP_COOKIE' => '',
            'HTTP_CONNECTION' => '',
            'REMOTE_ADDR' => '',
            'REMOTE_PORT' => '0',
            'REQUEST_TIME' => time()
        ];

        // Parse headers.
        list($httpHeader, $httpBody) = explode(self::HTTP_EOF_DOUBLE, $recvBuffer, 2);
        $headerData = explode(self::HTTP_EOF, $httpHeader);

        list($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SERVER_PROTOCOL']) = explode(' ',
            $headerData[0]);

        $httpPostBoundary = '';
        unset($headerData[0]);
        foreach ($headerData as $content) {
            // \r\n\r\n
            if (empty($content)) {
                continue;
            }
            list($key, $value) = explode(':', $content, 2);
            $key = str_replace('-', '_', strtoupper($key));
            $value = trim($value);
            $_SERVER['HTTP_' . $key] = $value;
            switch ($key) {
                // HTTP_HOST
                case 'HOST':
                    $tmp = explode(':', $value);
                    $_SERVER['SERVER_NAME'] = $tmp[0];
                    if (isset($tmp[1])) {
                        $_SERVER['SERVER_PORT'] = $tmp[1];
                    }
                    break;
                // cookie
                case 'COOKIE':
                    parse_str(str_replace('; ', '&', $_SERVER['HTTP_COOKIE']), $_COOKIE);
                    break;
                // content-type
                case 'CONTENT_TYPE':
                    if (!preg_match('/boundary="?(\S+)"?/', $value, $match)) {
                        $_SERVER['CONTENT_TYPE'] = $value;
                    } else {
                        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
                        $httpPostBoundary = '--' . $match[1];
                    }
                    break;
                case 'CONTENT_LENGTH':
                    $_SERVER['CONTENT_LENGTH'] = $value;
                    break;
            }
        }
        // Parse $_POST.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] === 'multipart/form-data') {
                self::parseUploadFiles($httpBody, $httpPostBoundary);
            } else {
                parse_str($httpBody, $_POST);
                // $GLOBALS['HTTP_RAW_POST_DATA']
                $GLOBALS['HTTP_RAW_REQUEST_DATA'] = $GLOBALS['HTTP_RAW_POST_DATA'] = $httpBody;
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            $GLOBALS['HTTP_RAW_REQUEST_DATA'] = $httpBody;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $GLOBALS['HTTP_RAW_REQUEST_DATA'] = $httpBody;
        }
        // QUERY_STRING
        $_SERVER['QUERY_STRING'] = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        if ($_SERVER['QUERY_STRING']) {
            // $GET
            parse_str($_SERVER['QUERY_STRING'], $_GET);
        } else {
            $_SERVER['QUERY_STRING'] = '';
        }
        // REQUEST
        $_REQUEST = array_merge($_GET, $_POST);
        // REMOTE_ADDR REMOTE_PORT
        $_SERVER['REMOTE_ADDR'] = $connection->getRemoteIp();
        $_SERVER['REMOTE_PORT'] = $connection->getRemotePort();

        return [
            'get' => $_GET,
            'post' => $_POST,
            'cookie' => $_COOKIE,
            'server' => $_SERVER,
            'files' => $_FILES
        ];
    }

    /**
     * Http encode.
     *
     * @param string $content
     * @param Tcp $connection
     * @return string
     */
    public static function encode($content, Connection $connection)
    {
        // Default http-code.
        if (!isset(HttpCache::$header['Http-Code'])) {
            $header = "HTTP/1.1 200 OK" . self::HTTP_EOF;
        } else {
            $header = HttpCache::$header['Http-Code'] . self::HTTP_EOF;
            unset(HttpCache::$header['Http-Code']);
        }
        // Content-Type
        if (!isset(HttpCache::$header['Content-Type'])) {
            $header .= "Content-Type: text/html;charset=utf-8" . self::HTTP_EOF;
        }
        // other headers
        foreach (HttpCache::$header as $key => $item) {
            if ('Set-Cookie' === $key && is_array($item)) {
                foreach ($item as $it) {
                    $header .= $it . self::HTTP_EOF;
                }
            } else {
                $header .= $item . self::HTTP_EOF;
            }
        }
        // header
        $header .= "Server: phspring/" . Ac::$version . self::HTTP_EOF . "Content-Length: " . strlen($content) . self::HTTP_EOF_DOUBLE;
        // save session
        self::sessionWriteClose();
        // the whole http package
        return $header . $content;
    }

    /**
     * 设置http头
     *
     * @return bool|void
     */
    public static function header($content, $replace = true, $httpResponseCode = 0)
    {
        if (PHP_SAPI != 'cli') {
            return $httpResponseCode ? header($content, $replace, $httpResponseCode) : header($content, $replace);
        }
        if (strpos($content, 'HTTP') === 0) {
            $key = 'Http-Code';
        } else {
            $key = strstr($content, ':', true);
            if (empty($key)) {
                return false;
            }
        }

        if ('location' === strtolower($key) && !$httpResponseCode) {
            return self::header($content, true, 302);
        }

        if (isset(HttpCache::$codes[$httpResponseCode])) {
            HttpCache::$header['Http-Code'] = 'HTTP/1.1 $httpResponseCode ' . HttpCache::$codes[$httpResponseCode];
            if ($key === 'Http-Code') {
                return true;
            }
        }

        if ($key === 'Set-Cookie') {
            HttpCache::$header[$key][] = $content;
        } else {
            HttpCache::$header[$key] = $content;
        }

        return true;
    }

    /**
     * Remove header.
     *
     * @param string $name
     * @return void
     */
    public static function headerRemove($name)
    {
        if (PHP_SAPI != 'cli') {
            header_remove($name);
            return;
        }
        unset(HttpCache::$header[$name]);
    }

    /**
     * Set cookie.
     *
     * @param string $name
     * @param string $value
     * @param integer $maxage
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httpOnly
     * @return bool|void
     */
    public static function setcookie(
        $name,
        $value = '',
        $maxage = 0,
        $path = '',
        $domain = '',
        $secure = false,
        $httpOnly = false
    ) {
        if (PHP_SAPI != 'cli') {
            return setcookie($name, $value, $maxage, $path, $domain, $secure, $httpOnly);
        }
        return self::header(
            'Set-Cookie: ' . $name . '=' . rawurlencode($value)
            . (empty($domain) ? '' : '; Domain=' . $domain)
            . (empty($maxage) ? '' : '; Max-Age=' . $maxage)
            . (empty($path) ? '' : '; Path=' . $path)
            . (!$secure ? '' : '; Secure')
            . (!$httpOnly ? '' : '; HttpOnly'), false);
    }

    /**
     * sessionStart
     *
     * @return bool
     */
    public static function sessionStart()
    {
        if (PHP_SAPI != 'cli') {
            return session_start();
        }

        self::tryGcSessions();

        if (HttpCache::$instance->sessionStarted) {
            echo "already sessionStarted" . PHP_EOL;
            return true;
        }
        HttpCache::$instance->sessionStarted = true;
        // Generate a SID.
        if (!isset($_COOKIE[HttpCache::$sessionName]) || !is_file(HttpCache::$sessionPath . '/ses' . $_COOKIE[HttpCache::$sessionName])) {
            $fileName = tempnam(HttpCache::$sessionPath, 'ses');
            if (!$fileName) {
                return false;
            }
            HttpCache::$instance->sessionFile = $fileName;
            $sessionId = substr(basename($fileName), strlen('ses'));
            return self::setcookie(
                HttpCache::$sessionName
                , $sessionId
                , ini_get('session.cookie_lifetime')
                , ini_get('session.cookie_path')
                , ini_get('session.cookie_domain')
                , ini_get('session.cookie_secure')
                , ini_get('session.cookie_httponly')
            );
        }
        if (!HttpCache::$instance->sessionFile) {
            HttpCache::$instance->sessionFile = HttpCache::$sessionPath . '/ses' . $_COOKIE[HttpCache::$sessionName];
        }
        // Read session from session file.
        if (HttpCache::$instance->sessionFile) {
            $raw = file_get_contents(HttpCache::$instance->sessionFile);
            if ($raw) {
                session_decode($raw);
            }
        }

        return true;
    }

    /**
     * Save session.
     *
     * @return bool
     */
    public static function sessionWriteClose()
    {
        if (PHP_SAPI != 'cli') {
            return session_write_close();
        }
        if (!empty(HttpCache::$instance->sessionStarted) && !empty($_SESSION)) {
            $session_str = session_encode();
            if ($session_str && HttpCache::$instance->sessionFile) {
                return file_put_contents(HttpCache::$instance->sessionFile, $session_str);
            }
        }

        return empty($_SESSION);
    }

    /**
     * End, like call exit in php-fpm.
     *
     * @param string $msg
     * @throws \Exception
     */
    public static function end($msg = '')
    {
        if (PHP_SAPI != 'cli') {
            exit($msg);
        }
        if ($msg) {
            echo $msg;
        }

        throw new \Exception('jump_exit');
    }

    /**
     * Get mime types.
     *
     * @return string
     */
    public static function getMimeTypesFile()
    {
        return __DIR__ . '/Http/mime.types';
    }

    /**
     * Parse $_FILES.
     *
     * @param string $httpBody
     * @param string $httpPostBoundary
     * @return void
     */
    protected static function parseUploadFiles($httpBody, $httpPostBoundary)
    {
        $httpBody = substr($httpBody, 0, strlen($httpBody) - (strlen($httpPostBoundary) + 4));
        $boundaryDataArr = explode($httpPostBoundary . self::HTTP_EOF, $httpBody);
        if ($boundaryDataArr[0] === '') {
            unset($boundaryDataArr[0]);
        }
        foreach ($boundaryDataArr as $boundaryDataBuffer) {
            list($boundaryHeaderBuffer, $boundaryValue) = explode(self::HTTP_EOF_DOUBLE, $boundaryDataBuffer, 2);
            // Remove \r\n from the end of buffer.
            $boundaryValue = substr($boundaryValue, 0, -2);
            $key = -1;
            foreach (explode("\r\n", $boundaryHeaderBuffer) as $item) {
                list($header_key, $headerValue) = explode(": ", $item);
                $header_key = strtolower($header_key);
                switch ($header_key) {
                    case "content-disposition":
                        $key++;
                        // Is file data.
                        if (preg_match('/name="(.*?)"; filename="(.*?)"$/', $headerValue, $match)) {
                            // Parse $_FILES.
                            $_FILES[$key] = [
                                'name' => $match[1],
                                'file_name' => $match[2],
                                'file_data' => $boundaryValue,
                                'file_size' => strlen($boundaryValue),
                            ];
                            continue;
                        } // Is post field.
                        else {
                            // Parse $_POST.
                            if (preg_match('/name="(.*?)"$/', $headerValue, $match)) {
                                $_POST[$match[1]] = $boundaryValue;
                            }
                        }
                        break;
                    case "content-type":
                        // add file_type
                        $_FILES[$key]['file_type'] = trim($headerValue);
                        break;
                }
            }
        }
    }

    /**
     * Try GC sessions.
     *
     * @return void
     */
    public static function tryGcSessions()
    {
        if (HttpCache::$sessionGcProbability <= 0 ||
            HttpCache::$sessionGcDivisor <= 0 ||
            rand(1, HttpCache::$sessionGcDivisor) > HttpCache::$sessionGcProbability
        ) {
            return;
        }

        $timeNow = time();
        foreach (glob(HttpCache::$sessionPath . '/ses*') as $file) {
            if (is_file($file) && $timeNow - filemtime($file) > HttpCache::$sessionGcMaxLifeTime) {
                unlink($file);
            }
        }
    }
}
