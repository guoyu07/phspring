#!/home/worker/php/bin/php
<?php
/**
 * This file is part of the phspring package.
 */

define('APPLICATION_NAME', 'demo');
define('ROOT_PATH', __DIR__ . '/..');
define('APPLICATION_ENV', $_ENV['PHSPRING_ENV'] ?? 'dev');
define('RUNTIME_DIR', ROOT_PATH . '/runtime/' . APPLICATION_NAME);
is_dir(RUNTIME_DIR) && @mkdir(RUNTIME_DIR, 0755, true);

require_once ROOT_PATH . '/vendor/autoload.php';

$config = ROOT_PATH . '/protected/config/' . APPLICATION_ENV . '/main.php';
(new \App\AppServer($config))->run();