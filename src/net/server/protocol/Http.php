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
    const HTTP_EOL = "\r\n";
    /**
     * double http eof
     */
    const HTTP_EOL_DOUBLE = "\r\n\r\n";

    /**
     * @var array
     */
    public static $codes = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Action Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
    ];
    /**
     * The supported HTTP methods
     * @var array
     */
    public static $verbs = [
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
        if (!strpos($recvBuffer, self::HTTP_EOL_DOUBLE)) {
            // Judge whether the package length exceeds the limit.
            if (strlen($recvBuffer) >= Tcp::$maxPackageSize) {
                $connection->close();
                return 0;
            }
            return 0;
        }

        list($header,) = explode(self::HTTP_EOL_DOUBLE, $recvBuffer, 2);
        $verb = substr($header, 0, strpos($header, ' '));
        if (in_array($verb, static::$verbs)) {
            return static::getRequestSize($header, $verb);
        } else {
            $connection->send('HTTP/1.1 400 Bad Request' . self::HTTP_EOL_DOUBLE, true);
            return 0;
        }
    }

    /**
     * Get whole size of the request
     * includes the request headers and request body.
     * @param string $header The request headers
     * @param string $verb The request verb
     * @return integer
     */
    protected static function getRequestSize($header, $verb)
    {
        if ($verb == 'GET') {
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
     * Parse $post、$get、$cookie.
     * @param string $recvBuffer
     * @param Tcp $connection
     * @return array
     */
    public static function decode($recvBuffer, Connection $connection)
    {
        $get = $post = $cookie = $files = [];
        $GLOBALS['HTTP_RAW_POST_DATA'] = '';
        // $server
        $server = [
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
        list($httpHeader, $httpBody) = explode(self::HTTP_EOL_DOUBLE, $recvBuffer, 2);
        $headerData = explode(self::HTTP_EOL, $httpHeader);

        list($server['REQUEST_METHOD'], $server['REQUEST_URI'], $server['SERVER_PROTOCOL']) = explode(' ',
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
            $server['HTTP_' . $key] = $value;
            switch ($key) {
                // HTTP_HOST
                case 'HOST':
                    $tmp = explode(':', $value);
                    $server['SERVER_NAME'] = $tmp[0];
                    if (isset($tmp[1])) {
                        $server['SERVER_PORT'] = $tmp[1];
                    }
                    break;
                // cookie
                case 'COOKIE':
                    parse_str(str_replace('; ', '&', $server['HTTP_COOKIE']), $cookie);
                    break;
                // content-type
                case 'CONTENT_TYPE':
                    if (!preg_match('/boundary="?(\S+)"?/', $value, $match)) {
                        $server['CONTENT_TYPE'] = $value;
                    } else {
                        $server['CONTENT_TYPE'] = 'multipart/form-data';
                        $httpPostBoundary = '--' . $match[1];
                    }
                    break;
                case 'CONTENT_LENGTH':
                    $server['CONTENT_LENGTH'] = $value;
                    break;
            }
        }
        // Parse $post.
        if ($server['REQUEST_METHOD'] === 'POST') {
            if (isset($server['CONTENT_TYPE']) && $server['CONTENT_TYPE'] === 'multipart/form-data') {
                self::parseUploadFiles($httpBody, $httpPostBoundary);
            } else {
                parse_str($httpBody, $post);
                // $GLOBALS['HTTP_RAW_POST_DATA']
                $GLOBALS['HTTP_RAW_REQUEST_DATA'] = $GLOBALS['HTTP_RAW_POST_DATA'] = $httpBody;
            }
        }
        if ($server['REQUEST_METHOD'] === 'PUT') {
            $GLOBALS['HTTP_RAW_REQUEST_DATA'] = $httpBody;
        }
        if ($server['REQUEST_METHOD'] === 'DELETE') {
            $GLOBALS['HTTP_RAW_REQUEST_DATA'] = $httpBody;
        }
        // QUERY_STRING
        $server['QUERY_STRING'] = parse_url($server['REQUEST_URI'], PHP_URL_QUERY);
        if ($server['QUERY_STRING']) {
            // $GET
            parse_str($server['QUERY_STRING'], $get);
        } else {
            $server['QUERY_STRING'] = '';
        }
        // REMOTE_ADDR REMOTE_PORT
        $server['REMOTE_ADDR'] = $connection->getRemoteIp();
        $server['REMOTE_PORT'] = $connection->getRemotePort();

        return [
            'get' => $get,
            'post' => $post,
            'cookie' => $cookie,
            'server' => $server,
            'files' => $files
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
        self::sessionWriteClose();
        return $content;
    }

    /**
     * @param array $headers
     * @return string
     */
    public static function packHeaders($headers = [])
    {
        if (!isset($headers['Http-Code'])) {
            $header = "HTTP/1.1 200 OK" . self::HTTP_EOL;
        } else {
            $header = $headers['Http-Code'] . self::HTTP_EOL;
            unset($headers['Http-Code']);
        }
        if (!isset($headers['Content-Type'])) {
            $header .= "Content-Type: text/html;charset=utf-8" . self::HTTP_EOL;
        }
        foreach ($headers as $key => $values) {
            if ($key === 'Set-Cookie' && is_array($values)) {
                foreach ($values as $value) {
                    $header .= $value . self::HTTP_EOL;
                }
            } else {
                $header .= $values . self::HTTP_EOL;
            }
        }
        $header .= self::HTTP_EOL_DOUBLE;

        return $header;
    }

    /**
     * 设置http头
     *
     * @return bool|void
     */
    public static function header($content, $replace = true, $httpResponseCode = 0)
    {
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
        return $name . '=' . rawurlencode($value)
            . (empty($domain) ? '' : '; Domain=' . $domain)
            . (empty($maxage) ? '' : '; Max-Age=' . $maxage)
            . (empty($path) ? '' : '; Path=' . $path)
            . (!$secure ? '' : '; Secure')
            . (!$httpOnly ? '' : '; HttpOnly');
    }

    /**
     * sessionStart
     *
     * @return bool
     */
    public static function sessionStart()
    {
        self::tryGcSessions();

        if (HttpCache::$instance->sessionStarted) {
            echo "already sessionStarted" . PHP_EOL;
            return true;
        }
        HttpCache::$instance->sessionStarted = true;
        // Generate a SID.
        if (!isset($cookie[HttpCache::$sessionName]) || !is_file(HttpCache::$sessionPath . '/ses' . $cookie[HttpCache::$sessionName])) {
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
            HttpCache::$instance->sessionFile = HttpCache::$sessionPath . '/ses' . $cookie[HttpCache::$sessionName];
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
     * Parse $files.
     *
     * @param string $httpBody
     * @param string $httpPostBoundary
     * @return void
     */
    protected static function parseUploadFiles($httpBody, $httpPostBoundary)
    {
        $httpBody = substr($httpBody, 0, strlen($httpBody) - (strlen($httpPostBoundary) + 4));
        $boundaryDataArr = explode($httpPostBoundary . self::HTTP_EOL, $httpBody);
        if ($boundaryDataArr[0] === '') {
            unset($boundaryDataArr[0]);
        }
        foreach ($boundaryDataArr as $boundaryDataBuffer) {
            list($boundaryHeaderBuffer, $boundaryValue) = explode(self::HTTP_EOL_DOUBLE, $boundaryDataBuffer, 2);
            // Remove \r\n from the end of buffer.
            $boundaryValue = substr($boundaryValue, 0, -2);
            $key = -1;
            foreach (explode(self::HTTP_EOL, $boundaryHeaderBuffer) as $item) {
                list($header_key, $headerValue) = explode(": ", $item);
                $header_key = strtolower($header_key);
                switch ($header_key) {
                    case "content-disposition":
                        $key++;
                        // Is file data.
                        if (preg_match('/name="(.*?)"; filename="(.*?)"$/', $headerValue, $match)) {
                            // Parse $files.
                            $files[$key] = [
                                'name' => $match[1],
                                'file_name' => $match[2],
                                'file_data' => $boundaryValue,
                                'file_size' => strlen($boundaryValue),
                            ];
                            continue;
                        } else { // Is post field.
                            // Parse $post.
                            if (preg_match('/name="(.*?)"$/', $headerValue, $match)) {
                                $post[$match[1]] = $boundaryValue;
                            }
                        }
                        break;
                    case "content-type":
                        // add file_type
                        $files[$key]['file_type'] = trim($headerValue);
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
