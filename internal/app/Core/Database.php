<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    public static function connect(array $config): PDO
    {
        $driver = strtolower((string) ($config['driver'] ?? 'mysql'));
        self::assertPdoDriverAvailable($driver);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if ($driver === 'sqlsrv') {
            return self::connectSqlServer($config, $options);
        }

        $serverDsn = sprintf('mysql:host=%s;port=%s;charset=%s', $config['host'], $config['port'], $config['charset']);
        $serverPdo = new PDO($serverDsn, $config['username'], $config['password'], $options);
        $serverPdo->exec(
            sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s_unicode_ci',
                str_replace('`', '``', $config['database']),
                $config['charset'],
                $config['charset']
            )
        );

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        return new PDO($dsn, $config['username'], $config['password'], $options);
    }

    private static function connectSqlServer(array $config, array $options): PDO
    {
        $server = trim((string) ($config['host'] ?? ''));
        $port = trim((string) ($config['port'] ?? ''));
        $serverSpec = $port !== '' ? ($server . ',' . $port) : $server;

        $dsn = sprintf(
            'sqlsrv:Server=%s;Database=%s;TrustServerCertificate=%s;MultipleActiveResultSets=%s',
            $serverSpec,
            $config['database'],
            !empty($config['trust_server_certificate']) ? '1' : '0',
            !empty($config['multiple_active_result_sets']) ? '1' : '0'
        );

        if (empty($config['trusted_connection'])) {
            return new PDO($dsn, $config['username'], $config['password'], $options);
        }

        try {
            return new PDO($dsn, null, null, $options);
        } catch (PDOException $e) {
            if (self::shouldFallbackToSqlAuth($e, $config)) {
                return new PDO($dsn, $config['username'], $config['password'], $options);
            }

            throw $e;
        }
    }

    private static function assertPdoDriverAvailable(string $driver): void
    {
        $availableDrivers = PDO::getAvailableDrivers();
        if (in_array($driver, $availableDrivers, true)) {
            return;
        }

        $availableList = $availableDrivers === [] ? 'none' : implode(', ', $availableDrivers);

        if ($driver === 'sqlsrv') {
            throw new PDOException(
                'Configured DB_DRIVER=sqlsrv but PDO SQL Server driver is not loaded. '
                . 'Install/enable pdo_sqlsrv for this PHP runtime and restart the web server. '
                . 'Installed PDO drivers: ' . $availableList
            );
        }

        throw new PDOException(
            'Configured DB_DRIVER=' . $driver . ' is not available. Installed PDO drivers: ' . $availableList
        );
    }

    private static function shouldFallbackToSqlAuth(PDOException $e, array $config): bool
    {
        $username = trim((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');
        if ($username === '' || $password === '') {
            return false;
        }

        $message = strtoupper($e->getMessage());
        return str_contains($message, 'ANONYMOUS LOGON')
            || str_contains($message, 'SQLSTATE[28000]')
            || str_contains($message, 'LOGIN FAILED FOR USER');
    }
}
