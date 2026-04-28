<?php declare(strict_types=1);

namespace App\Repositories;

use PDO;

class FeedbackRepository {
    public function __construct(private PDO $pdo) {}

    private function getDefaultStatusId(): int {
        $stmt = $this->pdo->query(
            'SELECT id FROM statuses WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 1'
        );
        $statusId = (int) ($stmt->fetchColumn() ?: 0);
        if ($statusId <= 0) {
            throw new \RuntimeException('No active statuses configured', 500);
        }
        return $statusId;
    }

    private function getStatusIdByName(string $name): int {
        $stmt = $this->pdo->prepare('SELECT id FROM statuses WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $statusId = (int) ($stmt->fetchColumn() ?: 0);
        if ($statusId <= 0) {
            throw new \RuntimeException('Invalid status selected', 422);
        }
        return $statusId;
    }

    public function getCategoryIdByName(string $name): int {
        $stmt = $this->pdo->prepare('SELECT id FROM categories WHERE name = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$name]);
        $categoryId = (int) ($stmt->fetchColumn() ?: 0);
        if ($categoryId <= 0) {
            throw new \RuntimeException('Invalid category selected', 422);
        }
        return $categoryId;
    }

    /**
     * Create a new feedback report
     */
    public function createReport(string $reference, int $categoryId, ?string $categoryOther, string $description): int {
        $defaultStatusId = $this->getDefaultStatusId();
        $stmt = $this->pdo->prepare(
            'INSERT INTO reports (reference_no, category_id, category_other, description, status_id, priority, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        
        $stmt->execute([
            $reference,
            $categoryId,
            $categoryOther,
            $description,
            $defaultStatusId,
            'Normal'
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Find report by reference number
     */
    public function findByReference(string $reference): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, s.name AS status,
                    COALESCE(r.category_other, c.name) AS category
             FROM reports r
             LEFT JOIN statuses s ON s.id = r.status_id
             LEFT JOIN categories c ON c.id = r.category_id
             WHERE r.reference_no = ?'
        );
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

    private function buildCaseWhereClause(array $filters, array &$params): string {
        $where = ' WHERE 1=1';

        if (!empty($filters['reference_no'])) {
            $where .= ' AND r.reference_no LIKE ?';
            $params[] = '%' . $filters['reference_no'] . '%';
        }

        if (!empty($filters['category'])) {
            $where .= ' AND c.name = ?';
            $params[] = $filters['category'];
        }

        if (!empty($filters['status'])) {
            $where .= ' AND s.name = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['date'])) {
            $where .= ' AND DATE(r.created_at) = ?';
            $params[] = $filters['date'];
        }

        return $where;
    }

    /**
     * List all reports for HR with filters
     */
    public function listCases(array $filters = []): array {
        $params = [];
        $query = 'SELECT r.*, s.name AS status,
                         COALESCE(r.category_other, c.name) AS category
                  FROM reports r
                  LEFT JOIN statuses s ON s.id = r.status_id
                  LEFT JOIN categories c ON c.id = r.category_id';
        $query .= $this->buildCaseWhereClause($filters, $params);

        $allowedSortBy = ['created_at', 'category', 'status', 'reference_no', 'priority'];
        $sortBy = in_array($filters['sort_by'] ?? 'created_at', $allowedSortBy, true)
            ? (string) $filters['sort_by']
            : 'created_at';
        $sortOrder = strtoupper((string) ($filters['sort_order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $sortColumnMap = [
            'created_at' => 'r.created_at',
            'category' => 'c.name',
            'status' => 's.name',
            'reference_no' => 'r.reference_no',
            'priority' => 'r.priority',
        ];
        $query .= ' ORDER BY ' . ($sortColumnMap[$sortBy] ?? 'r.created_at') . ' ' . $sortOrder;

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countCases(array $filters = []): int {
        $params = [];
        $query = 'SELECT COUNT(*)
                  FROM reports r
                  LEFT JOIN statuses s ON s.id = r.status_id
                  LEFT JOIN categories c ON c.id = r.category_id';
        $query .= $this->buildCaseWhereClause($filters, $params);

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function listCasesPaged(array $filters = [], int $page = 1, int $perPage = 10): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $params = [];
        $query = 'SELECT r.*, s.name AS status,
                         COALESCE(r.category_other, c.name) AS category
                  FROM reports r
                  LEFT JOIN statuses s ON s.id = r.status_id
                  LEFT JOIN categories c ON c.id = r.category_id';
        $query .= $this->buildCaseWhereClause($filters, $params);

        $allowedSortBy = ['created_at', 'category', 'status', 'reference_no', 'priority'];
        $sortBy = in_array($filters['sort_by'] ?? 'created_at', $allowedSortBy, true)
            ? (string) $filters['sort_by']
            : 'created_at';
        $sortOrder = strtoupper((string) ($filters['sort_order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $sortColumnMap = [
            'created_at' => 'r.created_at',
            'category' => 'c.name',
            'status' => 's.name',
            'reference_no' => 'r.reference_no',
            'priority' => 'r.priority',
        ];
        $query .= ' ORDER BY ' . ($sortColumnMap[$sortBy] ?? 'r.created_at') . ' ' . $sortOrder;
        $query .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * List public anonymized reports
     */
    public function listPublicReports(array $filters = []): array {
        $query = 'SELECT r.reference_no, COALESCE(r.category_other, c.name) AS category,
                         s.name AS status, r.anonymized_summary, r.outcome_comments, r.created_at
                  FROM reports r
                  LEFT JOIN statuses s ON s.id = r.status_id
                  LEFT JOIN categories c ON c.id = r.category_id
                  WHERE 1=1';
        $params = [];

        if (!empty($filters['reference_no'])) {
            $query .= ' AND r.reference_no LIKE ?';
            $params[] = '%' . $filters['reference_no'] . '%';
        }

        if (!empty($filters['category'])) {
            $query .= ' AND c.name = ?';
            $params[] = $filters['category'];
        }

        if (!empty($filters['status'])) {
            $query .= ' AND s.name = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['date'])) {
            $query .= ' AND DATE(r.created_at) = ?';
            $params[] = $filters['date'];
        }

        $query .= ' ORDER BY r.created_at DESC';

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
                if ($key === 'status') {
                    $updates[] = 'status_id = ?';
                    $params[] = $this->getStatusIdByName((string) $value);
                } else {
                    $updates[] = "$key = ?";
                    $params[] = $value;
                }
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
     * Get a single attachment by ID
     */
    public function getAttachmentById(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM attachments WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Log audit trail entry
     */
    public function logAudit(string $actor, string $action, string $reference, string $details): int {
        // Resolve report_id from reference_no for the FK; stays NULL if not found (e.g. pre-creation events)
        $reportIdStmt = $this->pdo->prepare('SELECT id FROM reports WHERE reference_no = ? LIMIT 1');
        $reportIdStmt->execute([$reference]);
        $reportId = ($reportIdStmt->fetchColumn() ?: null);

        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (report_id, actor, action, reference_no, details, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );

        $stmt->execute([$reportId, $actor, $action, $reference, $details]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Delete audit log entries older than $retentionDays (default 1825 = 5 years).
     * Returns the number of rows deleted.
     */
    public function pruneOldAuditLogs(int $retentionDays = 1825): int {
        $stmt = $this->pdo->prepare(
            'DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->execute([$retentionDays]);
        return (int) $stmt->rowCount();
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

    /**
     * Get active recipient emails by role.
     */
    public function getRecipientsByRole(string $role): array {
        $stmt = $this->pdo->prepare('SELECT email FROM users WHERE role = ? AND is_active = 1');
        $stmt->execute([$role]);
        return array_map(
            static fn (array $row): string => (string) $row['email'],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * Find unacknowledged reports that need a specific notification kind.
     */
    public function getUnacknowledgedReportsNeedingNotification(int $hours, string $kind): array {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.reference_no, COALESCE(r.category_other, c.name) AS category, r.created_at
             FROM reports r
             LEFT JOIN categories c ON c.id = r.category_id
             WHERE r.acknowledged_at IS NULL
               AND TIMESTAMPDIFF(HOUR, r.created_at, NOW()) >= ?
               AND NOT EXISTS (
                   SELECT 1
                   FROM notifications n
                   WHERE n.report_id = r.id AND n.kind = ?
               )'
        );

        $stmt->execute([$hours, $kind]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Dashboard trends grouped by year and quarter.
     */
    public function getQuarterlyCategoryTrends(): array {
        $stmt = $this->pdo->query(
            'SELECT YEAR(r.created_at) AS year_no,
                    QUARTER(r.created_at) AS quarter_no,
                    c.name AS category,
                    COUNT(*) AS total_cases
             FROM reports r
             LEFT JOIN categories c ON c.id = r.category_id
             GROUP BY YEAR(r.created_at), QUARTER(r.created_at), c.name
             ORDER BY year_no DESC, quarter_no DESC, c.name ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Dashboard aggregate status totals.
     */
    public function getStatusTotals(): array {
        $stmt = $this->pdo->query(
            'SELECT s.name AS status, COUNT(*) AS total
             FROM reports r
             LEFT JOIN statuses s ON s.id = r.status_id
             GROUP BY s.name
             ORDER BY total DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Category frequency summary: total cases and still-open cases per category.
     * Used to support prioritisation by frequency and potential impact (FRD §4.2).
     */
    public function getCategoryFrequencySummary(): array {
        $stmt = $this->pdo->query(
            'SELECT c.name AS category,
                    COUNT(*) AS total_cases,
                    SUM(CASE WHEN s.name NOT LIKE \'%completed%\' THEN 1 ELSE 0 END) AS open_cases
             FROM reports r
             LEFT JOIN statuses s ON s.id = r.status_id
             LEFT JOIN categories c ON c.id = r.category_id
             GROUP BY c.name
             ORDER BY open_cases DESC, total_cases DESC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
