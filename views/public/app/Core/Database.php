<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
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

        $this->connection = new PDO(
            $dsn,
            $this->config->databaseUser(),
            $this->config->databasePass(),
            $options
        );

        return $this->connection;
    }

    public function config(): Config
    {
        return $this->config;
    }
}
