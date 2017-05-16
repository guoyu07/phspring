<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\net\server;

use phspring\net\server\protocol\Http;

/**
 * Class Server
 * @package phspring\net\server
 */
class Server extends Worker
{
    /**
     * Mime mapping.
     * @var array
     */
    protected static $mimeTypeMap = [];

    /**
     * Virtual host to path mapping.
     * @var array ['workerman.net'=>'/home', 'www.workerman.net'=>'home/www']
     */
    protected $serverRoot = [];
    /**
     * Used to save user OnWorkerStart callback settings.
     * @var callback
     */
    protected $onWorkerStart = null;

    /**
     * Add virtual host.
     *
     * @param string $domain
     * @param string $rootPath
     * @return void
     */
    public function addRoot($domain, $rootPath)
    {
        $this->serverRoot[$domain] = $rootPath;
    }

    /**
     * Construct.
     *
     * @param string $socketName
     * @param array $options
     */
    public function __construct($socketName, $options = [])
    {
        list(, $address) = explode(':', $socketName, 2);
        parent::__construct('http:' . $address, $options);
        $this->name = 'Server';
    }

    /**
     * Run webserver instance.
     *
     * @see Workerman.Worker::run()
     */
    public function run()
    {
        $this->onWorkerStart = $this->onWorkerStart;
        $this->onWorkerStart = [$this, 'onWorkerStart'];
        $this->onMessage = [$this, 'onMessage'];
        parent::run();
    }

    /**
     * Emit when process start.
     *
     * @throws \Exception
     */
    public function onWorkerStart()
    {
        if (empty($this->serverRoot)) {
            throw new \Exception('server root not set, please use WebServer::addRoot($domain, $rootPath) to set server root path');
            exit(250);
        }
        // Init mimeMap.
        $this->initMimeTypeMap();
        // onWorkerStart callback.
        if ($this->onWorkerStart) {
            try {
                call_user_func($this->onWorkerStart, $this);
            } catch (\Throwable $e) {
                Util::log($e) && exit(250);
            }
        }
    }

    /**
     * Init mime map.
     *
     * @return void
     */
    public function initMimeTypeMap()
    {
        $mime_file = Http::getMimeTypesFile();
        if (!is_file($mime_file)) {
            $this->log("$mime_file mime.type file not fond");
            return;
        }
        $items = file($mime_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($items)) {
            $this->log("get $mime_file mime.type content fail");
            return;
        }
        foreach ($items as $content) {
            if (preg_match("/\s*(\S+)\s+(\S.+)/", $content, $match)) {
                $mime_type = $match[1];
                $workerman_file_extension_var = $match[2];
                $workerman_file_extension_array = explode(' ', substr($workerman_file_extension_var, 0, -1));
                foreach ($workerman_file_extension_array as $workerman_file_extension) {
                    self::$mimeTypeMap[$workerman_file_extension] = $mime_type;
                }
            }
        }
    }

    /**
     * Emit when http message coming.
     * @param Connection\TcpConnection $connection
     * @return void
     */
    public function onMessage($connection)
    {
        // REQUEST_URI.
        $workerman_url_info = parse_url($_SERVER['REQUEST_URI']);
        if (!$workerman_url_info) {
            Http::header('HTTP/1.1 400 Bad Request');
            $connection->close('<h1>400 Bad Request</h1>');
            return;
        }

        $workerman_path = isset($workerman_url_info['path']) ? $workerman_url_info['path'] : '/';

        $workerman_path_info = pathinfo($workerman_path);
        $workerman_file_extension = isset($workerman_path_info['extension']) ? $workerman_path_info['extension'] : '';
        if ($workerman_file_extension === '') {
            $workerman_path = ($len = strlen($workerman_path)) && $workerman_path[$len - 1] === '/' ? $workerman_path . 'index.php' : $workerman_path . '/index.php';
            $workerman_file_extension = 'php';
        }

        $workerman_root_dir = isset($this->serverRoot[$_SERVER['SERVER_NAME']]) ? $this->serverRoot[$_SERVER['SERVER_NAME']] : current($this->serverRoot);

        $workerman_file = "$workerman_root_dir/$workerman_path";

        if ($workerman_file_extension === 'php' && !is_file($workerman_file)) {
            $workerman_file = "$workerman_root_dir/index.php";
            if (!is_file($workerman_file)) {
                $workerman_file = "$workerman_root_dir/index.html";
                $workerman_file_extension = 'html';
            }
        }

        // File exsits.
        if (is_file($workerman_file)) {
            // Security check.
            if ((!($workerman_request_realpath = realpath($workerman_file)) || !($workerman_root_dir_realpath = realpath($workerman_root_dir))) || 0 !== strpos($workerman_request_realpath,
                    $workerman_root_dir_realpath)
            ) {
                Http::header('HTTP/1.1 400 Bad Request');
                $connection->close('<h1>400 Bad Request</h1>');
                return;
            }

            $workerman_file = realpath($workerman_file);

            // Request php file.
            if ($workerman_file_extension === 'php') {
                $workerman_cwd = getcwd();
                chdir($workerman_root_dir);
                ini_set('display_errors', 'off');
                ob_start();
                // Try to include php file.
                try {
                    // $_SERVER.
                    $_SERVER['REMOTE_ADDR'] = $connection->getRemoteIp();
                    $_SERVER['REMOTE_PORT'] = $connection->getRemotePort();
                    include $workerman_file;
                } catch (\Exception $e) {
                    // Jump_exit?
                    if ($e->getMessage() != 'jump_exit') {
                        echo $e;
                    }
                }
                $content = ob_get_clean();
                ini_set('display_errors', 'on');
                if (strtolower($_SERVER['HTTP_CONNECTION']) === "keep-alive") {
                    $connection->send($content);
                } else {
                    $connection->close($content);
                }
                chdir($workerman_cwd);
                return;
            }

            // Send file to client.
            return self::sendFile($connection, $workerman_file);
        } else {
            // 404
            Http::header("HTTP/1.1 404 Not Found");
            $connection->close('<html><head><title>404 File not found</title></head><body><center><h3>404 Not Found</h3></center></body></html>');
            return;
        }
    }

    /**
     * @param $connection
     * @param $filePath
     * @return mixed
     */
    public static function sendFile($connection, $filePath)
    {
        // Check 304.
        $info = stat($filePath);
        $modifiedTime = $info ? date('D, d M Y H:i:s', $info['mtime']) . ' ' . date_default_timezone_get() : '';
        if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $info) {
            // Http 304.
            if ($modifiedTime === $_SERVER['HTTP_IF_MODIFIED_SINCE']) {
                // 304
                Http::header('HTTP/1.1 304 Not Modified');
                // Send nothing but http headers..
                $connection->close('');
                return;
            }
        }

        // Http header.
        if ($modifiedTime) {
            $modifiedTime = "Last-Modified: $modifiedTime\r\n";
        }
        $fileSize = filesize($filePath);
        $fileInfo = pathinfo($filePath);
        $extension = $fileInfo['extension'] ?? '';
        $fileName = $fileInfo['filename'] ?? '';
        $header = "HTTP/1.1 200 OK\r\n";
        if (isset(self::$mimeTypeMap[$extension])) {
            $header .= "Content-Type: " . self::$mimeTypeMap[$extension] . "\r\n";
        } else {
            $header .= "Content-Type: application/octet-stream\r\n";
            $header .= "Content-Disposition: attachment; filename=\"$fileName\"\r\n";
        }
        $header .= "Connection: keep-alive\r\n";
        $header .= $modifiedTime;
        $header .= "Content-Length: $fileSize\r\n\r\n";
        $trunk_limit_size = 1024 * 1024;
        if ($fileSize < $trunk_limit_size) {
            return $connection->send($header . file_get_contents($filePath), true);
        }
        $connection->send($header, true);

        // Read file content from disk piece by piece and send to client.
        $connection->fileHandler = fopen($filePath, 'r');
        $writer = function () use ($connection) {
            // Send buffer not full.
            while (empty($connection->bufferFull)) {
                // Read from disk.
                $buffer = fread($connection->fileHandler, 8192);
                // Read eof.
                if ($buffer === '' || $buffer === false) {
                    return;
                }
                $connection->send($buffer, true);
            }
        };
        // Send buffer full.
        $connection->onBufferFull = function ($connection) {
            $connection->bufferFull = true;
        };
        // Send buffer drain.
        $connection->onBufferDrain = function ($connection) use ($writer) {
            $connection->bufferFull = false;
            $writer();
        };
        $writer();
    }
}
