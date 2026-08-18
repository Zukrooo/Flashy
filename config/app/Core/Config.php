<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Config
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $databaseDriver,
        private readonly string $databasePath,
        private readonly string $databaseHost,
        private readonly int $databasePort,
        private readonly string $databaseName,
        private readonly string $databaseUser,
        private readonly string $databasePass,
        private readonly string $databaseCharset
    ) {
    }

    public static function isConfigured(string $basePath): bool
    {
        return self::isLocalDevelopment()
            || self::hasEnvironmentConfiguration()
            || is_file(self::configFilePath($basePath));
    }

    public static function loadIfConfigured(string $basePath): ?self
    {
        if (!self::isConfigured($basePath)) {
            return null;
        }

        return self::load($basePath);
    }

    public static function load(string $basePath): self
    {
        if (self::isLocalDevelopment() && !self::hasEnvironmentConfiguration() && !is_file(self::configFilePath($basePath))) {
            return self::localSqlite($basePath);
        }

        $fileConfig = [];
        $configFile = self::configFilePath($basePath);

        if (is_file($configFile)) {
            $fileConfig = require $configFile;
        }

        $host = self::env('FLASHY_DB_HOST') ?? ($fileConfig['host'] ?? '');
        $port = (int) (self::env('FLASHY_DB_PORT') ?? ($fileConfig['port'] ?? 3306));
        $name = (string) (self::env('FLASHY_DB_NAME') ?? ($fileConfig['name'] ?? ''));
        $user = (string) (self::env('FLASHY_DB_USER') ?? ($fileConfig['user'] ?? ''));
        $pass = (string) (self::env('FLASHY_DB_PASS') ?? ($fileConfig['pass'] ?? ''));
        $charset = (string) (self::env('FLASHY_DB_CHARSET') ?? ($fileConfig['charset'] ?? 'utf8mb4'));

        if ($host === '' || $name === '' || $user === '') {
            throw new RuntimeException('Database configuration is incomplete. Complete the installer first.');
        }

        return new self(
            rtrim($basePath, '/'),
            'mysql',
            self::sqlitePath($basePath),
            $host,
            $port > 0 ? $port : 3306,
            $name,
            $user,
            $pass,
            $charset !== '' ? $charset : 'utf8mb4'
        );
    }

    public static function fromArray(string $basePath, array $values): self
    {
        $driver = strtolower(trim((string) ($values['driver'] ?? 'mysql')));

        if ($driver === 'sqlite') {
            $path = (string) ($values['path'] ?? self::sqlitePath($basePath));

            return new self(
                rtrim($basePath, '/'),
                'sqlite',
                self::normalizePath($path, $basePath),
                '',
                3306,
                '',
                '',
                '',
                'utf8mb4'
            );
        }

        $host = trim((string) ($values['host'] ?? ''));
        $name = trim((string) ($values['name'] ?? ''));
        $user = trim((string) ($values['user'] ?? ''));

        if ($host === '' || $name === '' || $user === '') {
            throw new RuntimeException('Database host, name, and user are required.');
        }

        return new self(
            rtrim($basePath, '/'),
            'mysql',
            self::sqlitePath($basePath),
            $host,
            max(1, (int) ($values['port'] ?? 3306)),
            $name,
            $user,
            (string) ($values['pass'] ?? ''),
            (string) (($values['charset'] ?? 'utf8mb4') ?: 'utf8mb4')
        );
    }

    public static function write(string $basePath, self $config): void
    {
        $configFile = self::configFilePath($basePath);
        $export = var_export([
            'host' => $config->databaseHost(),
            'port' => $config->databasePort(),
            'name' => $config->databaseName(),
            'user' => $config->databaseUser(),
            'pass' => $config->databasePass(),
            'charset' => $config->databaseCharset(),
        ], true);

        $contents = <<<PHP
<?php

declare(strict_types=1);

return {$export};
PHP;

        if (file_put_contents($configFile, $contents . PHP_EOL) === false) {
            throw new RuntimeException('Could not write config/database.php. Check file permissions on the config directory.');
        }
    }

    public static function configFilePath(string $basePath): string
    {
        return rtrim($basePath, '/') . '/config/database.php';
    }

    public static function isLocalDevelopment(): bool
    {
        if ((self::env('FLASHY_ENV') ?? '') === 'production') {
            return false;
        }

        if ((self::env('FLASHY_ENV') ?? '') === 'local') {
            return true;
        }

        if (PHP_SAPI === 'cli-server') {
            return true;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $serverName = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));

        foreach ([$host, $serverName] as $value) {
            if (
                $value === 'localhost'
                || str_starts_with($value, 'localhost:')
                || str_starts_with($value, '127.0.0.1')
                || str_starts_with($value, '[::1]')
            ) {
                return true;
            }
        }

        return false;
    }

    public function basePath(string $path = ''): string
    {
        return $path === ''
            ? $this->basePath
            : $this->basePath . '/' . ltrim($path, '/');
    }

    public function databaseDriver(): string
    {
        return $this->databaseDriver;
    }

    public function databasePath(): string
    {
        return $this->databasePath;
    }

    public function databaseDirectory(): string
    {
        return dirname($this->databasePath);
    }

    public function databaseHost(): string
    {
        return $this->databaseHost;
    }

    public function databasePort(): int
    {
        return $this->databasePort;
    }

    public function databaseName(): string
    {
        return $this->databaseName;
    }

    public function databaseUser(): string
    {
        return $this->databaseUser;
    }

    public function databasePass(): string
    {
        return $this->databasePass;
    }

    public function databaseCharset(): string
    {
        return $this->databaseCharset;
    }

    public function isSqlite(): bool
    {
        return $this->databaseDriver === 'sqlite';
    }

    public function isMysql(): bool
    {
        return $this->databaseDriver === 'mysql';
    }

    private static function hasEnvironmentConfiguration(): bool
    {
        return self::env('FLASHY_DB_HOST') !== null
            && self::env('FLASHY_DB_NAME') !== null
            && self::env('FLASHY_DB_USER') !== null;
    }

    private static function localSqlite(string $basePath): self
    {
        return new self(
            rtrim($basePath, '/'),
            'sqlite',
            self::sqlitePath($basePath),
            '',
            3306,
            '',
            '',
            '',
            'utf8mb4'
        );
    }

    private static function sqlitePath(string $basePath): string
    {
        return rtrim($basePath, '/') . '/data/flashy.sqlite';
    }

    private static function normalizePath(string $path, string $basePath): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }

    private static function env(string $key): ?string
    {
        $value = getenv($key);

        if ($value === false) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
