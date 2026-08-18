<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;

require_once __DIR__ . '/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

date_default_timezone_set('Europe/London');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = Config::loadIfConfigured(dirname(__DIR__));
$database = $config instanceof Config ? new Database($config) : null;
