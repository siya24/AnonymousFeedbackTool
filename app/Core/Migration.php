<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Migration
{
    public static function run(PDO $pdo): void
    {
        // ---------------------------------------------------------------
        // Base schema & seed data
        // ---------------------------------------------------------------
        $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');
        if ($schema === false) {
            throw new \RuntimeException('Could not read schema.sql');
        }
        $pdo->exec($schema);

        $users = file_get_contents(__DIR__ . '/../../database/users.sql');
        if ($users !== false) {
            $pdo->exec($users);
        }

        // ---------------------------------------------------------------
        // Login rate-limiting table (added post-launch)
        // ---------------------------------------------------------------
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS login_attempts (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    ip  VARCHAR(45) NOT NULL,
                    success TINYINT(1) NOT NULL DEFAULT 0,
                    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_la_ip_time (ip, attempted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable $e) {
            // Table already exists.
        }

        // ---------------------------------------------------------------
        // Legacy migration: reports.status (text) → status_id FK
        // Only applies to installations created before status_id was introduced.
        // ---------------------------------------------------------------

        // Add status_id column if missing (nullable first for backfill).
        try {
            $pdo->exec('ALTER TABLE reports ADD COLUMN status_id INT UNSIGNED NULL');
        } catch (\Throwable $e) {
            // Column already exists.
        }

        // Backfill status_id from the legacy status text column if it exists.
        try {
            $pdo->exec(
                'UPDATE reports r
                 JOIN statuses s ON s.name = r.status
                 SET r.status_id = s.id
                 WHERE r.status_id IS NULL'
            );
        } catch (\Throwable $e) {
            // Legacy status column absent — fresh install or already migrated.
        }

        // Any remaining nulls get the first active status.
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
            // Ignore.
        }

        // Enforce NOT NULL now that every row has a value.
        try {
            $pdo->exec('ALTER TABLE reports MODIFY COLUMN status_id INT UNSIGNED NOT NULL');
        } catch (\Throwable $e) {
            // Already non-nullable.
        }

        // Add index and FK for status_id.
        try {
            $pdo->exec('ALTER TABLE reports ADD INDEX idx_status_id (status_id)');
        } catch (\Throwable $e) {
            // Already exists.
        }

        try {
            $pdo->exec('ALTER TABLE reports ADD CONSTRAINT fk_reports_status FOREIGN KEY (status_id) REFERENCES statuses(id)');
        } catch (\Throwable $e) {
            // Already exists.
        }

        // Drop old status text column after FK is in place.
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM reports LIKE 'status'")->fetchAll();
            if (!empty($cols)) {
                $pdo->exec('ALTER TABLE reports DROP COLUMN status');
            }
        } catch (\Throwable $e) {
            // Column already removed or does not exist.
        }

        // ---------------------------------------------------------------
        // Legacy migration: reports.category (VARCHAR) → category_id FK + category_other
        // On fresh installs these columns already exist from schema.sql; all
        // ALTER TABLE statements are wrapped in try/catch so duplicates are ignored.
        // ---------------------------------------------------------------

        // Ensure "Other" category row exists before any backfill.
        try {
            $pdo->exec(
                "INSERT IGNORE INTO categories (name, is_active, sort_order, created_at, updated_at)
                 VALUES ('Other', 1, 999, NOW(), NOW())"
            );
        } catch (\Throwable $e) {
            // Already exists.
        }

        // Add category_other column (no AFTER clause — order is not significant).
        try {
            $pdo->exec("ALTER TABLE reports ADD COLUMN category_other VARCHAR(255) NULL COMMENT 'Free-text detail when category is Other'");
        } catch (\Throwable $e) {
            // Column already exists.
        }

        // Add category_id column (nullable initially for backfill).
        try {
            $pdo->exec('ALTER TABLE reports ADD COLUMN category_id INT UNSIGNED NULL');
        } catch (\Throwable $e) {
            // Column already exists.
        }

        // Backfill category_id for rows whose legacy category text matches a known category.
        try {
            $pdo->exec(
                'UPDATE reports r
                 JOIN categories c ON c.name = r.category
                 SET r.category_id = c.id
                 WHERE r.category_id IS NULL AND r.category IS NOT NULL'
            );
        } catch (\Throwable $e) {
            // Legacy category column absent — fresh install.
        }

        // Remaining unmatched rows map to "Other"; preserve original text in category_other.
        try {
            $pdo->exec(
                "UPDATE reports r
                 JOIN categories c ON c.name = 'Other'
                 SET r.category_id    = c.id,
                     r.category_other = r.category
                 WHERE r.category_id IS NULL AND r.category IS NOT NULL"
            );
        } catch (\Throwable $e) {
            // No unmatched rows or legacy column absent.
        }

        // Safety net: any still-null rows get the first active category.
        try {
            $pdo->exec(
                'UPDATE reports
                 SET category_id = (
                     SELECT id FROM categories
                     WHERE is_active = 1
                     ORDER BY sort_order ASC, name ASC
                     LIMIT 1
                 )
                 WHERE category_id IS NULL'
            );
        } catch (\Throwable $e) {
            // Ignore.
        }

        // Enforce NOT NULL now that every row has a value.
        try {
            $pdo->exec('ALTER TABLE reports MODIFY COLUMN category_id INT UNSIGNED NOT NULL');
        } catch (\Throwable $e) {
            // Already non-nullable.
        }

        // Add index and FK for category_id.
        try {
            $pdo->exec('ALTER TABLE reports ADD INDEX idx_category_id (category_id)');
        } catch (\Throwable $e) {
            // Already exists.
        }

        try {
            $pdo->exec('ALTER TABLE reports ADD CONSTRAINT fk_reports_category FOREIGN KEY (category_id) REFERENCES categories(id)');
        } catch (\Throwable $e) {
            // Already exists.
        }

        // Drop old category text column after FK is in place.
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM reports LIKE 'category'")->fetchAll();
            if (!empty($cols)) {
                $pdo->exec('ALTER TABLE reports DROP COLUMN category');
            }
        } catch (\Throwable $e) {
            // Column already removed or does not exist.
        }

        // ---------------------------------------------------------------
        // Add report_id FK to audit_logs
        // ON DELETE SET NULL preserves the audit trail if a report is deleted.
        // ---------------------------------------------------------------
        try {
            $pdo->exec('ALTER TABLE audit_logs ADD COLUMN report_id BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            // Column already exists.
        }

        // Backfill report_id from reference_no.
        try {
            $pdo->exec(
                'UPDATE audit_logs al
                 JOIN reports r ON r.reference_no = al.reference_no
                 SET al.report_id = r.id
                 WHERE al.report_id IS NULL'
            );
        } catch (\Throwable $e) {
            // Ignore.
        }

        try {
            $pdo->exec('ALTER TABLE audit_logs ADD INDEX idx_audit_report_id (report_id)');
        } catch (\Throwable $e) {
            // Already exists.
        }

        try {
            $pdo->exec('ALTER TABLE audit_logs ADD CONSTRAINT fk_audit_logs_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE SET NULL');
        } catch (\Throwable $e) {
            // Already exists.
        }
    }
}
