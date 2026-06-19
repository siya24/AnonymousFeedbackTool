<?php declare(strict_types=1);

namespace App\Repositories;

use PDO;

class CoInvestigatorRepository {
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

    public function addCoInvestigator(string $feedbackId, string $userId, ?string $addedByUserId = null): bool {
        $id = self::generateUuid();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO feedback_co_investigators (id, feedback_id, user_id, added_at, added_by_user_id)
                  VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?)'
            );
            return $stmt->execute([$id, $feedbackId, $userId, $addedByUserId]);
        } catch (\PDOException $e) {
            // Unique constraint violation (already a co-investigator)
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    public function removeCoInvestigator(string $feedbackId, string $userId): bool {
        $stmt = $this->pdo->prepare(
            'DELETE FROM feedback_co_investigators
             WHERE feedback_id = ? AND user_id = ?'
        );
        return $stmt->execute([$feedbackId, $userId]);
    }

    public function getCoInvestigators(string $feedbackId): array {
        $stmt = $this->pdo->prepare(
            'SELECT fci.id, fci.user_id, u.name, u.email, u.role, fci.added_at,
                    added_by_user.name AS added_by_name
             FROM feedback_co_investigators fci
             LEFT JOIN users u ON u.id = fci.user_id
             LEFT JOIN users added_by_user ON added_by_user.id = fci.added_by_user_id
             WHERE fci.feedback_id = ?
             ORDER BY fci.added_at ASC'
        );
        $stmt->execute([$feedbackId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isCoInvestigator(string $feedbackId, string $userId): bool {
        $stmt = $this->pdo->prepare(
            'SELECT TOP 1 1 FROM feedback_co_investigators
             WHERE feedback_id = ? AND user_id = ?
             '
        );
        $stmt->execute([$feedbackId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function canUserAccessFeedback(string $feedbackId, string $userId): bool {
        $stmt = $this->pdo->prepare(
            'SELECT TOP 1 1 FROM feedbacks
             WHERE id = ? AND (assigned_to_user_id = ? OR id IN (
                SELECT feedback_id FROM feedback_co_investigators WHERE user_id = ?
             ))
             '
        );
        $stmt->execute([$feedbackId, $userId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function removeAllCoInvestigators(string $feedbackId): bool {
        $stmt = $this->pdo->prepare(
            'DELETE FROM feedback_co_investigators WHERE feedback_id = ?'
        );
        return $stmt->execute([$feedbackId]);
    }

    public function getUserById(string $userId): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT TOP 1 id, name, email FROM users WHERE id = ?'
        );
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
