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
        'assignment_roles',
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

        try {
            $hasAssignmentRolesTable = (bool) $pdo->query("SHOW TABLES LIKE 'assignment_roles'")->fetchColumn();
            if (!$hasAssignmentRolesTable) {
                $pdo->exec(
                    'CREATE TABLE assignment_roles (
                        id CHAR(36) NOT NULL PRIMARY KEY,
                        name VARCHAR(120) NOT NULL UNIQUE,
                        is_active TINYINT(1) NOT NULL DEFAULT 1,
                        created_by_user_id CHAR(36) NULL,
                        updated_by_user_id CHAR(36) NULL,
                        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                        created_at DATETIME NOT NULL,
                        updated_at DATETIME NOT NULL,
                        CONSTRAINT fk_assignment_roles__created_by_user_id__users FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
                        CONSTRAINT fk_assignment_roles__updated_by_user_id__users FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
                        INDEX idx_assignment_roles_is_active (is_active),
                        INDEX idx_assignment_roles_sort_order (sort_order)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
            }

            if (!self::hasColumn($pdo, 'feedbacks', 'assigned_to_user_id')) {
                $pdo->exec('ALTER TABLE feedbacks ADD COLUMN assigned_to_user_id CHAR(36) NULL AFTER stage_id');
                $pdo->exec('ALTER TABLE feedbacks ADD CONSTRAINT fk_feedbacks__assigned_to_user_id__users FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL');
                $pdo->exec('CREATE INDEX idx_assigned_to_user_id ON feedbacks (assigned_to_user_id)');
            }

            if (!self::hasColumn($pdo, 'feedbacks', 'assigned_role_id')) {
                $pdo->exec('ALTER TABLE feedbacks ADD COLUMN assigned_role_id CHAR(36) NULL AFTER assigned_to_user_id');
                $pdo->exec('ALTER TABLE feedbacks ADD CONSTRAINT fk_feedbacks__assigned_role_id__assignment_roles FOREIGN KEY (assigned_role_id) REFERENCES assignment_roles(id) ON DELETE SET NULL');
                $pdo->exec('CREATE INDEX idx_assigned_role_id ON feedbacks (assigned_role_id)');
            }

            if (!self::hasColumn($pdo, 'feedbacks', 'assigned_at')) {
                $pdo->exec('ALTER TABLE feedbacks ADD COLUMN assigned_at DATETIME NULL AFTER assigned_role_id');
            }

            if (!self::hasColumn($pdo, 'users', 'ad_username')) {
                $pdo->exec('ALTER TABLE users ADD COLUMN ad_username VARCHAR(120) NULL AFTER email');
                $pdo->exec('CREATE INDEX idx_ad_username ON users (ad_username)');
            }

            if (!self::hasColumn($pdo, 'users', 'first_name')) {
                $pdo->exec('ALTER TABLE users ADD COLUMN first_name VARCHAR(120) NULL AFTER name');
                $pdo->exec('CREATE INDEX idx_first_name ON users (first_name)');
            }

            if (!self::hasColumn($pdo, 'users', 'last_name')) {
                $pdo->exec('ALTER TABLE users ADD COLUMN last_name VARCHAR(120) NULL AFTER first_name');
                $pdo->exec('CREATE INDEX idx_last_name ON users (last_name)');
            }

            if (!self::hasColumn($pdo, 'users', 'department_name')) {
                $pdo->exec('ALTER TABLE users ADD COLUMN department_name VARCHAR(255) NULL AFTER role');
            }

            if (!self::hasColumn($pdo, 'users', 'employee_number')) {
                $pdo->exec('ALTER TABLE users ADD COLUMN employee_number VARCHAR(120) NULL AFTER role');
                $pdo->exec('CREATE INDEX idx_employee_number ON users (employee_number)');
            }

            if (!self::hasColumn($pdo, 'users', 'position_title')) {
                $pdo->exec('ALTER TABLE users ADD COLUMN position_title VARCHAR(255) NULL AFTER department_name');
            }

            if (!self::hasColumn($pdo, 'users', 'office_location')) {
                $pdo->exec('ALTER TABLE users ADD COLUMN office_location VARCHAR(255) NULL AFTER position_title');
            }

            if (!self::hasColumn($pdo, 'users', 'can_assign_cases')) {
                $pdo->exec('ALTER TABLE users ADD COLUMN can_assign_cases TINYINT(1) NOT NULL DEFAULT 0 AFTER office_location');
                $pdo->exec('CREATE INDEX idx_can_assign_cases ON users (can_assign_cases)');
            }
        } catch (\Throwable $e) {
            
        }
    }
}
