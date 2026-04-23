<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Migration
{
    public static function run(PDO $pdo): void
    {
        $sql = file_get_contents(__DIR__ . '/../../database/schema.sql');
        if ($sql === false) {
            throw new \RuntimeException('Could not read schema.sql');
        }

        $pdo->exec($sql);
    }
}
