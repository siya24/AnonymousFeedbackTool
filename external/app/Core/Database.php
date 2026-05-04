<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    public static function connect(array $config): PDO
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

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

        $pdo = new PDO($dsn, $config['username'], $config['password'], $options);

        return $pdo;
    }
}
