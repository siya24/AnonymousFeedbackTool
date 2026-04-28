<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class FeedbackModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createReport(string $category, string $description, ?string $categoryOther = null): array
    {
        $referenceNo = $this->generateReference('AF');
        $now = gmdate('Y-m-d H:i:s');

        $categoryStmt = $this->pdo->prepare('SELECT id FROM categories WHERE name = ? AND is_active = 1 LIMIT 1');
        $categoryStmt->execute([$category]);
        $categoryId = (int) ($categoryStmt->fetchColumn() ?: 0);
        if ($categoryId === 0) {
            throw new \RuntimeException('Invalid category.');
        }

        $defaultStatusStmt = $this->pdo->query('SELECT id FROM statuses WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 1');
        $defaultStatusId = (int) ($defaultStatusStmt->fetchColumn() ?: 0);
        if ($defaultStatusId === 0) {
            throw new \RuntimeException('No active statuses configured.');
        }

        $normalizedOther = ($category === 'Other' && $categoryOther !== null && $categoryOther !== '')
            ? $categoryOther
            : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO reports (reference_no, category_id, category_other, description, status_id, priority, created_at, updated_at)
             VALUES (:reference_no, :category_id, :category_other, :description, :status_id, :priority, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':reference_no'   => $referenceNo,
            ':category_id'    => $categoryId,
            ':category_other' => $normalizedOther,
            ':description'    => $description,
            ':status_id'      => $defaultStatusId,
            ':priority'       => 'Normal',
            ':created_at'     => $now,
            ':updated_at'     => $now,
        ]);

        return [
            'id'           => (int) $this->pdo->lastInsertId(),
            'reference_no' => $referenceNo,
        ];
    }

    public function createUpdate(string $referenceNo, string $updateText): array
    {
        $report = $this->findByReference($referenceNo);
        if ($report === null) {
            throw new \RuntimeException('Reference not found.');
        }

        $updateReference = $this->generateReference('UPD');
        $now = gmdate('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO report_updates (report_id, update_reference_no, update_text, created_at)
             VALUES (:report_id, :update_reference_no, :update_text, :created_at)'
        );
        $stmt->execute([
            ':report_id' => $report['id'],
            ':update_reference_no' => $updateReference,
            ':update_text' => $updateText,
            ':created_at' => $now,
        ]);

        $this->pdo->prepare('UPDATE reports SET updated_at = :updated_at WHERE id = :id')
            ->execute([':updated_at' => $now, ':id' => $report['id']]);

        return [
            'update_id' => (int) $this->pdo->lastInsertId(),
            'update_reference_no' => $updateReference,
            'report_id' => (int) $report['id'],
        ];
    }

    public function findByReference(string $referenceNo): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, s.name AS status, COALESCE(r.category_other, c.name) AS category
             FROM reports r
             LEFT JOIN statuses s ON s.id = r.status_id
             LEFT JOIN categories c ON c.id = r.category_id
             WHERE r.reference_no = :reference_no'
        );
        $stmt->execute([':reference_no' => strtoupper($referenceNo)]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listPublicReports(array $filters = []): array
    {
        $sql = 'SELECT r.reference_no, COALESCE(r.category_other, c.name) AS category,
                       s.name AS status, r.anonymized_summary, r.outcome_comments, r.created_at
                FROM reports r
                LEFT JOIN statuses s ON s.id = r.status_id
                LEFT JOIN categories c ON c.id = r.category_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['reference_no'])) {
            $sql .= ' AND r.reference_no = :reference_no';
            $params[':reference_no'] = strtoupper($filters['reference_no']);
        }
        if (!empty($filters['category'])) {
            $sql .= ' AND c.name LIKE :category';
            $params[':category'] = '%' . $filters['category'] . '%';
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND s.name = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['date'])) {
            $sql .= ' AND DATE(r.created_at) = :date';
            $params[':date'] = $filters['date'];
        }

        $sql .= ' ORDER BY r.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listCases(array $filters = []): array
    {
        $sql = 'SELECT r.id, r.reference_no, COALESCE(r.category_other, c.name) AS category,
                       s.name AS status, r.priority, r.stage, r.acknowledged_at, r.created_at, r.updated_at
                FROM reports r
                LEFT JOIN statuses s ON s.id = r.status_id
                LEFT JOIN categories c ON c.id = r.category_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['reference_no'])) {
            $sql .= ' AND r.reference_no = :reference_no';
            $params[':reference_no'] = strtoupper($filters['reference_no']);
        }
        if (!empty($filters['category'])) {
            $sql .= ' AND c.name LIKE :category';
            $params[':category'] = '%' . $filters['category'] . '%';
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND s.name = :status';
            $params[':status'] = $filters['status'];
        }

        $sql .= ' ORDER BY r.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateCase(string $referenceNo, array $payload): void
    {
        $report = $this->findByReference($referenceNo);
        if ($report === null) {
            throw new \RuntimeException('Case not found.');
        }

        $newStatus = $payload['status'] ?? $report['status'];

        if ($newStatus === 'Investigation completed' && trim((string) ($payload['outcome_comments'] ?? '')) === '') {
            throw new \RuntimeException('Outcome comments are mandatory when status is Investigation completed.');
        }

        // Resolve status name → id.
        $statusStmt = $this->pdo->prepare('SELECT id FROM statuses WHERE name = ? LIMIT 1');
        $statusStmt->execute([$newStatus]);
        $statusId = (int) ($statusStmt->fetchColumn() ?: 0);
        if ($statusId === 0) {
            throw new \RuntimeException('Invalid status selected.');
        }

        $acknowledgedAt = !empty($payload['acknowledge'])
            ? ($report['acknowledged_at'] ?: gmdate('Y-m-d H:i:s'))
            : $report['acknowledged_at'];

        $stmt = $this->pdo->prepare(
            'UPDATE reports
             SET status_id          = :status_id,
                 priority           = :priority,
                 stage              = :stage,
                 anonymized_summary = :anonymized_summary,
                 action_taken       = :action_taken,
                 outcome_comments   = :outcome_comments,
                 internal_notes     = :internal_notes,
                 acknowledged_at    = :acknowledged_at,
                 updated_at         = :updated_at
             WHERE id = :id'
        );

        $stmt->execute([
            ':status_id'          => $statusId,
            ':priority'           => $payload['priority'] ?? $report['priority'],
            ':stage'              => $payload['stage'] ?? $report['stage'],
            ':anonymized_summary' => $payload['anonymized_summary'] ?? $report['anonymized_summary'],
            ':action_taken'       => $payload['action_taken'] ?? $report['action_taken'],
            ':outcome_comments'   => $payload['outcome_comments'] ?? $report['outcome_comments'],
            ':internal_notes'     => $payload['internal_notes'] ?? $report['internal_notes'],
            ':acknowledged_at'    => $acknowledgedAt,
            ':updated_at'         => gmdate('Y-m-d H:i:s'),
            ':id'                 => $report['id'],
        ]);
    }

    public function getCaseDetail(string $referenceNo): ?array
    {
        $report = $this->findByReference($referenceNo);
        if ($report === null) {
            return null;
        }

        $updatesStmt = $this->pdo->prepare('SELECT update_reference_no, update_text, created_at FROM report_updates WHERE report_id = :report_id ORDER BY created_at DESC');
        $updatesStmt->execute([':report_id' => $report['id']]);

        $attachmentsStmt = $this->pdo->prepare('SELECT original_name, mime_type, size_bytes, created_at FROM attachments WHERE report_id = :report_id ORDER BY created_at DESC');
        $attachmentsStmt->execute([':report_id' => $report['id']]);

        $auditStmt = $this->pdo->prepare('SELECT actor, action, details, created_at FROM audit_logs WHERE reference_no = :reference_no ORDER BY created_at DESC');
        $auditStmt->execute([':reference_no' => $report['reference_no']]);

        return [
            'report' => $report,
            'updates' => $updatesStmt->fetchAll(),
            'attachments' => $attachmentsStmt->fetchAll(),
            'audit' => $auditStmt->fetchAll(),
        ];
    }

    public function saveAttachment(int $reportId, ?int $updateId, string $originalName, string $storedName, string $mimeType, int $size): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO attachments (report_id, report_update_id, original_name, stored_name, mime_type, size_bytes, created_at)
             VALUES (:report_id, :report_update_id, :original_name, :stored_name, :mime_type, :size_bytes, :created_at)'
        );

        $stmt->execute([
            ':report_id' => $reportId,
            ':report_update_id' => $updateId,
            ':original_name' => $originalName,
            ':stored_name' => $storedName,
            ':mime_type' => $mimeType,
            ':size_bytes' => $size,
            ':created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function logAudit(string $actor, string $action, string $referenceNo, string $details): void
    {
        $refUpper = strtoupper($referenceNo);

        $reportIdStmt = $this->pdo->prepare('SELECT id FROM reports WHERE reference_no = ? LIMIT 1');
        $reportIdStmt->execute([$refUpper]);
        $reportId = ($reportIdStmt->fetchColumn() ?: null);

        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (report_id, actor, action, reference_no, details, created_at)
             VALUES (:report_id, :actor, :action, :reference_no, :details, :created_at)'
        );

        $stmt->execute([
            ':report_id'    => $reportId,
            ':actor'        => $actor,
            ':action'       => $action,
            ':reference_no' => $refUpper,
            ':details'      => $details,
            ':created_at'   => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function logNotification(int $reportId, string $kind, string $recipient): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (report_id, kind, recipient, sent_at)
             VALUES (:report_id, :kind, :recipient, :sent_at)'
        );

        $stmt->execute([
            ':report_id' => $reportId,
            ':kind' => $kind,
            ':recipient' => $recipient,
            ':sent_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function generateReference(string $prefix): string
    {
        return sprintf('%s-%s-%s', $prefix, gmdate('Ymd'), strtoupper(bin2hex(random_bytes(3))));
    }
}
