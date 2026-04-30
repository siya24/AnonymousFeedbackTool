<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Migration
{
    
    private const DROP_ORDER = [
        'notifications',
        'audit_logs',
        'attachments',
        'report_updates',
        'feedbacks',
        'login_attempts',
        'categories',
        'stages',
        'statuses',
        'users',
    ];

    private static function getColumnType(PDO $pdo, string $table, string $column): string
    {
        $stmt = $pdo->prepare(
            "SELECT DATA_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return strtolower((string) ($stmt->fetchColumn() ?: ''));
    }

    private static function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1"
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }

    public static function run(PDO $pdo): void
    {
        
        
        
        
        try {
            $hasReports   = (bool) $pdo->query("SHOW TABLES LIKE 'reports'")->fetchColumn();
            $hasFeedbacks = (bool) $pdo->query("SHOW TABLES LIKE 'feedbacks'")->fetchColumn();
            if ($hasReports && !$hasFeedbacks) {
                $pdo->exec('RENAME TABLE reports TO feedbacks');
            }
        } catch (\Throwable $e) {
            
        }

        
        
        
        
        
        
        
        
        
        try {
            $categoryIdType = self::getColumnType($pdo, 'categories', 'id');

            $needsRebuild = false;

            
            if ($categoryIdType !== '' && $categoryIdType !== 'char') {
                $needsRebuild = true;
            }

            
            if ($categoryIdType !== '') {
                $requiredColumns = [
                    ['categories', 'created_by_user_id'],
                    ['categories', 'updated_by_user_id'],
                    ['statuses', 'created_by_user_id'],
                    ['statuses', 'updated_by_user_id'],
                    ['stages', 'created_by_user_id'],
                    ['stages', 'updated_by_user_id'],
                    ['feedbacks', 'updated_by_user_id'],
                ];

                foreach ($requiredColumns as [$table, $column]) {
                    if (!self::hasColumn($pdo, $table, $column)) {
                        $needsRebuild = true;
                        break;
                    }
                }
            }

            if ($needsRebuild) {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                foreach (self::DROP_ORDER as $table) {
                    $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
        } catch (\Throwable $e) {
            
        }

        
        
        
        
        $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');
        if ($schema === false) {
            throw new \RuntimeException('Could not read schema.sql');
        }
        $pdo->exec($schema);

        $users = file_get_contents(__DIR__ . '/../../database/users.sql');
        if ($users !== false) {
            $pdo->exec($users);
        }
    }
}
