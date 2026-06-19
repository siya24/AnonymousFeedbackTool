<?php
declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ServerException;
use PDO;

final class Migration
{
    
    private const DROP_ORDER = [
        'notifications',
        'audit_logs',
        'attachments',
        'report_updates',
        'feedbacks',
        'user_roles',
        'login_attempts',
        'assignment_roles',
        'provinces',
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
        self::renameLegacyReportsTable($pdo);
        self::rebuildLegacySchemaIfRequired($pdo);
        self::applyBaseSchema($pdo);
        self::ensureExtendedSchema($pdo);
    }

    private static function renameLegacyReportsTable(PDO $pdo): void
    {
        try {
            $hasReports = (bool) $pdo->query("SHOW TABLES LIKE 'reports'")->fetchColumn();
            $hasFeedbacks = (bool) $pdo->query("SHOW TABLES LIKE 'feedbacks'")->fetchColumn();
            if ($hasReports && !$hasFeedbacks) {
                $pdo->exec('RENAME TABLE reports TO feedbacks');
            }
        } catch (\Throwable $e) {
            error_log('Migration renameLegacyReportsTable warning: ' . $e->getMessage());
        }
    }

    private static function rebuildLegacySchemaIfRequired(PDO $pdo): void
    {
        try {
            if (!self::legacySchemaNeedsRebuild($pdo)) {
                return;
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach (self::DROP_ORDER as $table) {
                $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (\Throwable $e) {
            error_log('Migration rebuildLegacySchemaIfRequired warning: ' . $e->getMessage());
        }
    }

    private static function legacySchemaNeedsRebuild(PDO $pdo): bool
    {
        $categoryIdType = self::getColumnType($pdo, 'categories', 'id');
        $needsRebuild = false;
        if ($categoryIdType === '') {
            return false;
        }

        if ($categoryIdType !== 'char') {
            $needsRebuild = true;
        }

        $requiredColumns = [
            ['categories', 'created_by_user_id'],
            ['categories', 'updated_by_user_id'],
            ['statuses', 'created_by_user_id'],
            ['statuses', 'updated_by_user_id'],
            ['stages', 'created_by_user_id'],
            ['stages', 'updated_by_user_id'],
            ['feedbacks', 'updated_by_user_id'],
        ];

        if (!$needsRebuild) {
            foreach ($requiredColumns as [$table, $column]) {
                if (!self::hasColumn($pdo, $table, $column)) {
                    $needsRebuild = true;
                    break;
                }
            }
        }

        return $needsRebuild;
    }

    private static function applyBaseSchema(PDO $pdo): void
    {
        $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');
        if ($schema === false) {
            throw new ServerException('Could not read schema.sql');
        }
        $pdo->exec($schema);

        $users = file_get_contents(__DIR__ . '/../../database/users.sql');
        if ($users !== false) {
            $pdo->exec($users);
        }
    }

    private static function ensureExtendedSchema(PDO $pdo): void
    {
        try {
            self::ensureAssignmentRolesTable($pdo);
            self::ensureUserRolesTable($pdo);
            self::ensureProvincesTable($pdo);
            self::ensureFeedbackAssignmentColumns($pdo);
            self::ensureFeedbackProvinceColumn($pdo);
            self::ensureFeedbackReporterFields($pdo);
            self::ensureUserDirectoryColumns($pdo);
        } catch (\Throwable $e) {
            error_log('Migration ensureExtendedSchema warning: ' . $e->getMessage());
        }
    }

    private static function ensureAssignmentRolesTable(PDO $pdo): void
    {
        $hasAssignmentRolesTable = (bool) $pdo->query("SHOW TABLES LIKE 'assignment_roles'")->fetchColumn();
        if ($hasAssignmentRolesTable) {
            return;
        }

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

    private static function ensureFeedbackAssignmentColumns(PDO $pdo): void
    {
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
    }

    private static function ensureProvincesTable(PDO $pdo): void
    {
        $hasProvincesTable = (bool) $pdo->query("SHOW TABLES LIKE 'provinces'")->fetchColumn();
        if (!$hasProvincesTable) {
            $pdo->exec(
                'CREATE TABLE provinces (
                    id CHAR(36) NOT NULL PRIMARY KEY,
                    name VARCHAR(120) NOT NULL UNIQUE,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    INDEX idx_provinces_is_active (is_active),
                    INDEX idx_provinces_sort_order (sort_order)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        $pdo->exec(
            "INSERT IGNORE INTO provinces (id, name, is_active, sort_order, created_at, updated_at) VALUES
            (UUID(), 'Eastern Cape',   1, 1, NOW(), NOW()),
            (UUID(), 'Free State',     1, 2, NOW(), NOW()),
            (UUID(), 'Gauteng',        1, 3, NOW(), NOW()),
            (UUID(), 'KwaZulu-Natal',  1, 4, NOW(), NOW()),
            (UUID(), 'Limpopo',        1, 5, NOW(), NOW()),
            (UUID(), 'Mpumalanga',     1, 6, NOW(), NOW()),
            (UUID(), 'Northern Cape',  1, 7, NOW(), NOW()),
            (UUID(), 'North West',     1, 8, NOW(), NOW()),
            (UUID(), 'Western Cape',   1, 9, NOW(), NOW())"
        );
    }

    private static function ensureFeedbackProvinceColumn(PDO $pdo): void
    {
        if (!self::hasColumn($pdo, 'feedbacks', 'province_id')) {
            $pdo->exec('ALTER TABLE feedbacks ADD COLUMN province_id CHAR(36) NULL AFTER stage_id');
        }

        $hasFk = (bool) $pdo->query(
            "SELECT 1
             FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND CONSTRAINT_NAME = 'fk_feedbacks__province_id__provinces'
             LIMIT 1"
        )->fetchColumn();
        if (!$hasFk) {
            $pdo->exec(
                'ALTER TABLE feedbacks
                 ADD CONSTRAINT fk_feedbacks__province_id__provinces
                 FOREIGN KEY (province_id) REFERENCES provinces(id)'
            );
        }

        $hasIndex = (bool) $pdo->query("SHOW INDEX FROM feedbacks WHERE Key_name = 'idx_province_id'")->fetchColumn();
        if (!$hasIndex) {
            $pdo->exec('CREATE INDEX idx_province_id ON feedbacks (province_id)');
        }
    }

    private static function ensureUserRolesTable(PDO $pdo): void
    {
        $hasUserRolesTable = (bool) $pdo->query("SHOW TABLES LIKE 'user_roles'")->fetchColumn();
        if (!$hasUserRolesTable) {
            $pdo->exec(
                'CREATE TABLE user_roles (
                    id CHAR(36) NOT NULL PRIMARY KEY,
                    user_id CHAR(36) NOT NULL,
                    role_id CHAR(36) NOT NULL,
                    created_at DATETIME NOT NULL,
                    CONSTRAINT fk_user_roles__user_id__users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    CONSTRAINT fk_user_roles__role_id__assignment_roles FOREIGN KEY (role_id) REFERENCES assignment_roles(id) ON DELETE CASCADE,
                    UNIQUE KEY uk_user_roles_user_role (user_id, role_id),
                    INDEX idx_user_roles_user_id (user_id),
                    INDEX idx_user_roles_role_id (role_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            return;
        }

        $legacyUnique = (bool) $pdo->query("SHOW INDEX FROM user_roles WHERE Key_name = 'uk_user_roles_user'")->fetchColumn();
        if ($legacyUnique) {
            $pdo->exec('ALTER TABLE user_roles DROP INDEX uk_user_roles_user');
        }

        $compositeUnique = (bool) $pdo->query("SHOW INDEX FROM user_roles WHERE Key_name = 'uk_user_roles_user_role'")->fetchColumn();
        if (!$compositeUnique) {
            $pdo->exec('ALTER TABLE user_roles ADD UNIQUE KEY uk_user_roles_user_role (user_id, role_id)');
        }
    }

    private static function ensureFeedbackReporterFields(PDO $pdo): void
    {
        if (!self::hasColumn($pdo, 'feedbacks', 'reporter_feedback')) {
            $pdo->exec('ALTER TABLE feedbacks ADD COLUMN reporter_feedback TEXT NULL AFTER anonymized_summary');
        }
    }

    private static function ensureUserDirectoryColumns(PDO $pdo): void
    {
        self::ensureColumn($pdo, 'users', 'ad_username', 'ALTER TABLE users ADD COLUMN ad_username VARCHAR(120) NULL AFTER email', 'CREATE INDEX idx_ad_username ON users (ad_username)');
        self::ensureColumn($pdo, 'users', 'first_name', 'ALTER TABLE users ADD COLUMN first_name VARCHAR(120) NULL AFTER name', 'CREATE INDEX idx_first_name ON users (first_name)');
        self::ensureColumn($pdo, 'users', 'last_name', 'ALTER TABLE users ADD COLUMN last_name VARCHAR(120) NULL AFTER first_name', 'CREATE INDEX idx_last_name ON users (last_name)');
        self::ensureColumn($pdo, 'users', 'department_name', 'ALTER TABLE users ADD COLUMN department_name VARCHAR(255) NULL AFTER role');
        self::ensureColumn($pdo, 'users', 'employee_number', 'ALTER TABLE users ADD COLUMN employee_number VARCHAR(120) NULL AFTER role', 'CREATE INDEX idx_employee_number ON users (employee_number)');
        self::ensureColumn($pdo, 'users', 'position_title', 'ALTER TABLE users ADD COLUMN position_title VARCHAR(255) NULL AFTER department_name');
        self::ensureColumn($pdo, 'users', 'office_location', 'ALTER TABLE users ADD COLUMN office_location VARCHAR(255) NULL AFTER position_title');
        self::ensureColumn($pdo, 'users', 'can_assign_cases', 'ALTER TABLE users ADD COLUMN can_assign_cases TINYINT(1) NOT NULL DEFAULT 0 AFTER office_location', 'CREATE INDEX idx_can_assign_cases ON users (can_assign_cases)');
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $alterSql, ?string $indexSql = null): void
    {
        if (self::hasColumn($pdo, $table, $column)) {
            return;
        }

        $pdo->exec($alterSql);
        if ($indexSql !== null) {
            $pdo->exec($indexSql);
        }
    }
}
