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

        // Migrate legacy installations that stored status text directly on reports.
        try {
            $pdo->exec('ALTER TABLE reports ADD COLUMN status_id INT UNSIGNED NULL AFTER description');
        } catch (\Throwable $e) {
            // Column already exists.
        }

        // Backfill status_id from legacy status text if present.
        try {
            $pdo->exec(
                'UPDATE reports r
                 LEFT JOIN statuses s ON s.name = r.status
                 SET r.status_id = s.id
                 WHERE r.status_id IS NULL'
            );
        } catch (\Throwable $e) {
            // Legacy status column may not exist in new installs.
        }

        // Ensure any remaining nulls get a default active status.
        try {
            $pdo->exec(
                'UPDATE reports
                 SET status_id = (
                    SELECT id FROM statuses
                    WHERE is_active = 1
                    ORDER BY sort_order ASC, name ASC
                    LIMIT 1
                 )
                 WHERE status_id IS NULL'
            );
        } catch (\Throwable $e) {
            // Ignore non-fatal migration differences.
        }

        try {
            $pdo->exec('ALTER TABLE reports MODIFY COLUMN status_id INT UNSIGNED NOT NULL');
        } catch (\Throwable $e) {
            // Already non-nullable or cannot be altered in this pass.
        }

        try {
            $pdo->exec('ALTER TABLE reports ADD INDEX idx_status_id (status_id)');
        } catch (\Throwable $e) {
            // Index already exists.
        }

        try {
            $pdo->exec('ALTER TABLE reports ADD CONSTRAINT fk_reports_status FOREIGN KEY (status_id) REFERENCES statuses(id)');
        } catch (\Throwable $e) {
            // Constraint already exists or cannot be added until data is clean.
        }
    }
}
