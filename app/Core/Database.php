<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private ?PDO $connection = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        if ($this->config->isSqlite()) {
            self::assertSqliteDriverAvailable();

            $directory = $this->config->databaseDirectory();

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            try {
                $this->connection = new PDO('sqlite:' . $this->config->databasePath());
                $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->connection->exec('PRAGMA foreign_keys = ON');
            } catch (PDOException $exception) {
                throw new RuntimeException(
                    'Could not open the local SQLite database file for development.',
                    0,
                    $exception
                );
            }

            return $this->connection;
        }

        self::assertMysqlDriverAvailable();

        if (
            $this->config->databaseName() === ''
            || $this->config->databaseUser() === ''
            || $this->config->databaseHost() === ''
        ) {
            throw new RuntimeException('MySQL database host, name, and user must be configured.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config->databaseHost(),
            $this->config->databasePort(),
            $this->config->databaseName(),
            $this->config->databaseCharset()
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
        }

        try {
            $this->connection = new PDO(
                $dsn,
                $this->config->databaseUser(),
                $this->config->databasePass(),
                $options
            );
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Could not connect to MySQL. Check that pdo_mysql is enabled and that the host, port, database name, username, and password are correct.',
                0,
                $exception
            );
        }

        return $this->connection;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public static function assertMysqlDriverAvailable(): void
    {
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException(
                'The pdo_mysql PHP extension is not installed or enabled for this PHP runtime. Enable pdo_mysql in the PHP version serving this app, then try again.'
            );
        }
    }

    public static function assertSqliteDriverAvailable(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException(
                'The pdo_sqlite PHP extension is not installed or enabled for this PHP runtime. Enable pdo_sqlite in the PHP version serving this app, then try again.'
            );
        }
    }
}
