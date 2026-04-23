<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Migration
{
    public static function run(PDO $pdo): void
    {
        $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');
        if ($schema === false) {
            throw new \RuntimeException('Could not read schema.sql');
        }

        $pdo->exec($schema);

        // Run users migration
        $users = file_get_contents(__DIR__ . '/../../database/users.sql');
        if ($users !== false) {
            $pdo->exec($users);
        }
    }
}
