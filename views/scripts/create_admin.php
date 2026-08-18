<?php

declare(strict_types=1);

use App\Core\Migrator;
use App\Core\Config;
use App\Core\Database;
use App\Repositories\UserRepository;

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!$database instanceof \App\Core\Database) {
    $config = Config::fromArray(dirname(__DIR__), [
        'driver' => 'sqlite',
        'path' => 'data/flashy.sqlite',
    ]);
    $database = new Database($config);
}

Migrator::migrate($database);

$email = null;
$password = null;
$firstName = 'Admin';
$lastName = '';

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--email=')) {
        $email = substr($argument, 8);
    }

    if (str_starts_with($argument, '--password=')) {
        $password = substr($argument, 11);
    }

    if (str_starts_with($argument, '--first-name=')) {
        $firstName = substr($argument, 13);
    }

    if (str_starts_with($argument, '--last-name=')) {
        $lastName = substr($argument, 12);
    }
}

if ($email === null || $password === null || $email === '' || $password === '') {
    fwrite(STDERR, "Usage: php scripts/create_admin.php --email=admin@example.com --password=secret [--first-name=Admin] [--last-name=User]" . PHP_EOL);
    exit(1);
}

$users = new UserRepository($database->connection());

if ($users->findByEmail($email) !== null) {
    fwrite(STDERR, "Admin user already exists for {$email}" . PHP_EOL);
    exit(1);
}

$users->create($email, password_hash($password, PASSWORD_DEFAULT), true, $firstName, $lastName);

echo "Admin user created for: {$email}" . PHP_EOL;
