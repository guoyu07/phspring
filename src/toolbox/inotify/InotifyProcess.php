<?php
/**
 * This file is part of the phspring package.
 */
namespace phspring\toolbox\inotify;

/**
 * Class InotifyProcess
 * @package phspring\toolbox\inotify
 */
class InotifyProcess
{
    const RELOAD_SIG = 'reload_sig';
    /**
     * @var string
     */
    public $monitorDir;
    /**
     * @var
     */
    public $inotifyFd;
    /**
     * @var
     */
    public $managePid;
    /**
     * @var
     */
    public $server;

    public function __construct($server)
    {
        echo "Start autoReload\n";
        $this->server = $server;
        $this->monitorDir = realpath(ROOT_PATH . '/');
        if (!extension_loaded('inotify')) {
            throw new \Exception('Non-install inotify ext.');
        } else {
            $this->monitor();
        }
    }

    public function monitor()
    {
        global $monitorFiles;
        // 初始化inotify句柄
        $this->inotifyFd = inotify_init();
        // 设置为非阻塞
        stream_set_blocking($this->inotifyFd, 0);
        // 递归遍历目录里面的文件
        $dirIterator = new \RecursiveDirectoryIterator($this->monitorDir);
        $iterator = new \RecursiveIteratorIterator($dirIterator);
        foreach ($iterator as $file) {
            // 只监控php文件
            if (pathinfo($file, PATHINFO_EXTENSION) != 'php') {
                continue;
            }
            // 把文件加入inotify监控，这里只监控了IN_MODIFY文件更新事件
            $wd = inotify_add_watch($this->inotifyFd, $file, IN_MODIFY);
            $monitorFiles[$wd] = $file;
        }
        // 监控inotify句柄可读事件
        swoole_event_add($this->inotifyFd, function ($inotifyFd) {
            global $monitorFiles;
            // 读取有哪些文件事件
            $events = inotify_read($inotifyFd);
            if ($events) {
                // 检查哪些文件被更新了
                foreach ($events as $ev) {
                    // 更新的文件
                    $file = $monitorFiles[$ev['wd']];
                    echo "[RELOAD]  " . $file . " update\n";
                    unset($monitorFiles[$ev['wd']]);
                    // 需要把文件重新加入监控
                    $wd = inotify_add_watch($inotifyFd, $file, IN_MODIFY);
                    $monitorFiles[$wd] = $file;
                }
                //reload
                $this->server->reload();
            }
        }, null, SWOOLE_EVENT_READ);
    }
}

