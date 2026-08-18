<?php

declare(strict_types=1);

use App\Core\Migrator;
use App\Core\Config;
use App\Core\Database;

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!$database instanceof \App\Core\Database) {
    $config = Config::fromArray(dirname(__DIR__), [
        'driver' => 'sqlite',
        'path' => 'data/flashy.sqlite',
    ]);
    $database = new Database($config);
}

$result = Migrator::migrate($database);

if (($result['driver'] ?? '') === 'sqlite') {
    echo 'Database ready for SQLite: ' . $result['path'] . PHP_EOL;
    exit(0);
}

echo sprintf(
    'Database ready for MySQL: %s @ %s:%d',
    $result['database'],
    $result['host'],
    $result['port']
) . PHP_EOL;
