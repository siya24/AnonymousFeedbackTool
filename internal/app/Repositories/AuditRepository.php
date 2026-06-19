<?php declare(strict_types=1);

namespace App\Repositories;

use PDO;

class AuditRepository {
    public function __construct(private PDO $pdo) {}

    private static function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    public function logAudit(string $actor, string $action, string $reference, string $details, ?string $actorUserId = null): string {
        $feedbackIdStmt = $this->pdo->prepare('SELECT TOP 1 id FROM feedbacks WHERE reference_no = ?');
        $feedbackIdStmt->execute([$reference]);
        $feedbackId = ($feedbackIdStmt->fetchColumn() ?: null);

        $id = self::generateUuid();

        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (id, feedback_id, actor, actor_user_id, action, reference_no, details, created_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([$id, $feedbackId, $actor, $actorUserId, $action, $reference, $details]);
        return $id;
    }

    public function pruneOldAuditLogs(int $retentionDays = 1825): int {
        $stmt = $this->pdo->prepare(
            'DELETE FROM audit_logs WHERE created_at < DATEADD(DAY, -CAST(? AS INT), CURRENT_TIMESTAMP)'
        );
        $stmt->execute([$retentionDays]);
        return (int) $stmt->rowCount();
    }

    public function getReportAudit(string $reference): array {
        $stmt = $this->pdo->prepare('SELECT * FROM audit_logs WHERE reference_no = ? ORDER BY created_at DESC');
        $stmt->execute([$reference]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function logNotification(string $feedbackId, string $kind, string $recipient): string {
        $id = self::generateUuid();

        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (id, feedback_id, kind, recipient, sent_at)
               VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([$id, $feedbackId, $kind, $recipient]);
        return $id;
    }

    public function getRecipientsByRole(string $role): array {
        $normalized = strtolower(trim($role));
                $stmt = $this->pdo->prepare(
                        "SELECT DISTINCT u.email
                         FROM users u
                         LEFT JOIN user_roles ur ON ur.user_id = u.id
                         LEFT JOIN assignment_roles ar ON ar.id = ur.role_id AND ar.is_active = 1
                         WHERE u.is_active = 1
                             AND u.email IS NOT NULL
                             AND LTRIM(RTRIM(u.email)) <> ''
                             AND (LOWER(u.role) = ? OR LOWER(ar.name) = ?)
                         ORDER BY u.email ASC"
                );
        $stmt->execute([$normalized, $normalized]);
        return array_map(
            static fn (array $row): string => (string) $row['email'],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function getActiveUserEmailsByIdentifiers(array $identifiers): array
    {
        $normalized = array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $identifiers
        ), static fn (string $value): bool => $value !== ''));

        if ($normalized === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalized), '?'));
        $params = array_merge($normalized, $normalized, $normalized);
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT email
             FROM users
             WHERE is_active = 1
               AND email IS NOT NULL
               AND LTRIM(RTRIM(email)) <> ''
               AND (
                    LOWER(email) IN ({$placeholders})
                    OR LOWER(COALESCE(ad_username, '')) IN ({$placeholders})
                    OR LOWER(COALESCE(name, '')) IN ({$placeholders})
               )
             ORDER BY email ASC"
        );
        $stmt->execute($params);

        return array_map(
            static fn(array $row): string => (string) $row['email'],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function getUnacknowledgedReportsNeedingNotification(int $hours, string $kind): array {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.reference_no, COALESCE(r.category_other, c.name) AS category, r.created_at
             FROM feedbacks r
             LEFT JOIN categories c ON c.id = r.category_id
             WHERE r.acknowledged_at IS NULL
                             AND DATEDIFF(HOUR, r.created_at, CURRENT_TIMESTAMP) >= ?
               AND NOT EXISTS (
                   SELECT 1 FROM notifications n
                   WHERE n.feedback_id = r.id AND n.kind = ?
               )'
        );

        $stmt->execute([$hours, $kind]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingNewFeedbackNotifications(): array
    {
        $stmt = $this->pdo->query(
            "SELECT f.id, f.reference_no, COALESCE(f.category_other, c.name) AS category, f.created_at
             FROM feedbacks f
             LEFT JOIN categories c ON c.id = f.category_id
             WHERE NOT EXISTS (
                 SELECT 1
                 FROM notifications n
                 WHERE n.feedback_id = f.id
                   AND n.kind = 'new_feedback'
             )
             ORDER BY f.created_at ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingFollowUpNotifications(): array
    {
        $stmt = $this->pdo->query(
            "SELECT ru.feedback_id AS id,
                    f.reference_no,
                    COALESCE(f.category_other, c.name) AS category,
                    ru.created_at,
                    ru.update_reference_no,
                    CONCAT(
                        'fup:',
                        LOWER(LEFT(CONVERT(VARCHAR(40), HASHBYTES('SHA1', COALESCE(ru.update_reference_no, '')), 2), 12))
                    ) AS notification_kind
             FROM report_updates ru
             INNER JOIN feedbacks f ON f.id = ru.feedback_id
             LEFT JOIN categories c ON c.id = f.category_id
             WHERE NOT EXISTS (
                 SELECT 1
                 FROM notifications n
                 WHERE n.feedback_id = ru.feedback_id
                   AND n.kind = CONCAT(
                       'fup:',
                       LOWER(LEFT(CONVERT(VARCHAR(40), HASHBYTES('SHA1', COALESCE(ru.update_reference_no, '')), 2), 12))
                   )
             )
             ORDER BY ru.created_at ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

