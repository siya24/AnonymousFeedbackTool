<?php declare(strict_types=1);

namespace App\Repositories;

use PDO;

class AttachmentRepository
{
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

    public function saveAttachment(
        string $feedbackId,
        ?string $updateId,
        string $originalName,
        string $storedName,
        string $mimeType,
        int $size
    ): string {
        $id = self::generateUuid();

        $stmt = $this->pdo->prepare(
            'INSERT INTO attachments (id, feedback_id, report_update_id, original_name, stored_name, mime_type, size_bytes, created_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([$id, $feedbackId, $updateId, $originalName, $storedName, $mimeType, $size]);
        return $id;
    }

    public function getReportAttachments(string $feedbackId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM attachments WHERE feedback_id = ? ORDER BY created_at DESC');
        $stmt->execute([$feedbackId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAttachmentById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM attachments WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
