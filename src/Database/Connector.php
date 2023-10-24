<?php

declare(strict_types=1);

namespace BasePHP\Database;

use InvalidArgumentException;
use PDO;

class Connector
{
    protected array $options = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    /**
     * Create a DSN string from a configuration.
     *
     * @param array $config
     * @return string
     */
    protected function getDsn(array $config): string
    {
        return $this->hasSocket($config)
            ? $this->getSocketDsn($config)
            : $this->getHostDsn($config);
    }

    /**
     * Determine if the given configuration array has a UNIX socket value.
     *
     * @param array $config
     * @return bool
     */
    protected function hasSocket(array $config): bool
    {
        return !empty($config['unix_socket']);
    }

    /**
     * Get the DSN string for a socket configuration.
     *
     * @param array $config
     * @return string
     */
    protected function getSocketDsn(array $config): string
    {
        if (!isset($config['unix_socket'])) {
            throw new InvalidArgumentException('The "unix_socket" option must be specified');
        }

        if (!isset($config['database'])) {
            throw new InvalidArgumentException('The database configuration must be specified');
        }

        return "mysql:unix_socket={$config['unix_socket']};dbname={$config['database']}";
    }

    /**
     * Get the DSN string for a host / port configuration.
     *
     * @param array $config
     * @return string
     */
    protected function getHostDsn(array $config): string
    {
        if (!isset($config['host'])) {
            throw new InvalidArgumentException('The host configuration must be specified');
        }

        if (!isset($config['database'])) {
            throw new InvalidArgumentException('The database configuration must be specified');
        }

        if (isset($config['port'])) {
            return "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        }

        return "mysql:host={$config['host']};dbname={$config['database']}";
    }

    public function getOptions(array $config)
    {
        $options = $config['options'] ?? [];

        return array_diff_key($this->options, $options) + $options;
    }

    public function getConnection(array $config): PDO
    {
        $dsn = $this->getDsn($config);

        $options = $this->getOptions($config);

        [$username, $password] = [
            $config['username'] ?? null,
            $config['password'] ?? null
        ];

        return new PDO($dsn, $username, $password, $options);
    }
}