<?php declare(strict_types=1);

namespace App\Repositories;

use PDO;

class FeedbackRepository {
    public function __construct(private PDO $pdo) {}

    /**
     * Create a new feedback report
     */
    public function createReport(string $reference, string $category, string $description): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reports (reference_no, category, description, status, priority, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        );
        
        $stmt->execute([
            $reference,
            $category,
            $description,
            'Investigation pending',
            'Normal'
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Find report by reference number
     */
    public function findByReference(string $reference): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM reports WHERE reference_no = ?');
        $stmt->execute([$reference]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get report with all related data
     */
    public function getDetailedReport(string $reference): ?array {
        $report = $this->findByReference($reference);
        
        if (!$report) {
            return null;
        }

        return [
            'report' => $report,
            'updates' => $this->getReportUpdates((int)$report['id']),
            'attachments' => $this->getReportAttachments((int)$report['id']),
            'audit' => $this->getReportAudit($reference)
        ];
    }

    /**
     * List all reports for HR with filters
     */
    public function listCases(array $filters = []): array {
        $query = 'SELECT * FROM reports WHERE 1=1';
        $params = [];

        if (!empty($filters['reference_no'])) {
            $query .= ' AND reference_no LIKE ?';
            $params[] = '%' . $filters['reference_no'] . '%';
        }

        if (!empty($filters['category'])) {
            $query .= ' AND category = ?';
            $params[] = $filters['category'];
        }

        if (!empty($filters['status'])) {
            $query .= ' AND status = ?';
            $params[] = $filters['status'];
        }

        $query .= ' ORDER BY created_at DESC';

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List public anonymized reports
     */
    public function listPublicReports(array $filters = []): array {
        $query = 'SELECT reference_no, category, status, anonymized_summary, outcome_comments, created_at 
                  FROM reports WHERE status != "Investigation pending"';
        $params = [];

        if (!empty($filters['reference_no'])) {
            $query .= ' AND reference_no LIKE ?';
            $params[] = '%' . $filters['reference_no'] . '%';
        }

        if (!empty($filters['category'])) {
            $query .= ' AND category = ?';
            $params[] = $filters['category'];
        }

        if (!empty($filters['status'])) {
            $query .= ' AND status = ?';
            $params[] = $filters['status'];
        }

        $query .= ' ORDER BY created_at DESC';

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update report status and metadata
     */
    public function updateReport(string $reference, array $data): bool {
        $allowed = ['priority', 'stage', 'status', 'anonymized_summary', 'action_taken', 
                   'outcome_comments', 'internal_notes', 'acknowledged_at'];
        
        $updates = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $reference;
        $query = 'UPDATE reports SET ' . implode(', ', $updates) . ', updated_at = NOW() 
                  WHERE reference_no = ?';

        $stmt = $this->pdo->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Create follow-up update
     */
    public function createUpdate(int $reportId, string $updateReference, string $updateText): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO report_updates (report_id, update_reference_no, update_text, created_at)
             VALUES (?, ?, ?, NOW())'
        );
        
        $stmt->execute([$reportId, $updateReference, $updateText]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Get all updates for a report
     */
    public function getReportUpdates(int $reportId): array {
        $stmt = $this->pdo->prepare('SELECT * FROM report_updates WHERE report_id = ? ORDER BY created_at ASC');
        $stmt->execute([$reportId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Save attachment
     */
    public function saveAttachment(int $reportId, ?int $updateId, string $originalName, 
                                  string $storedName, string $mimeType, int $size): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO attachments (report_id, report_update_id, original_name, stored_name, mime_type, size_bytes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        
        $stmt->execute([$reportId, $updateId, $originalName, $storedName, $mimeType, $size]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Get attachments for report
     */
    public function getReportAttachments(int $reportId): array {
        $stmt = $this->pdo->prepare('SELECT * FROM attachments WHERE report_id = ? ORDER BY created_at DESC');
        $stmt->execute([$reportId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Log audit trail entry
     */
    public function logAudit(string $actor, string $action, string $reference, string $details): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (actor, action, reference_no, details, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        
        $stmt->execute([$actor, $action, $reference, $details]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Get audit trail for reference
     */
    public function getReportAudit(string $reference): array {
        $stmt = $this->pdo->prepare('SELECT * FROM audit_logs WHERE reference_no = ? ORDER BY created_at DESC');
        $stmt->execute([$reference]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Log notification
     */
    public function logNotification(int $reportId, string $kind, string $recipient): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (report_id, kind, recipient, sent_at)
             VALUES (?, ?, ?, NOW())'
        );
        
        $stmt->execute([$reportId, $kind, $recipient]);
        return (int) $this->pdo->lastInsertId();
    }
}
