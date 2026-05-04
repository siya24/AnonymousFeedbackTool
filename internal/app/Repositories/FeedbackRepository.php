<?php declare(strict_types=1);

namespace App\Repositories;

use PDO;

class FeedbackRepository {
    public function __construct(private PDO $pdo) {}

    private static function generateUuid(): string
    {
        return sprintf('%08x-%04x-%04x-%04x-%012x',
            random_int(0, 0xFFFFFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0x0FFF) | 0x4000,
            random_int(0, 0x3FFF) | 0x8000,
            random_int(0, 0xFFFFFFFFFFFF)
        );
    }

    private function getDefaultStatusId(): string {
        $stmt = $this->pdo->query(
            'SELECT id FROM statuses WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 1'
        );
        $statusId = (string) ($stmt->fetchColumn() ?: '');
        if ($statusId === '') {
            throw new \RuntimeException('No active statuses configured', 500);
        }
        return $statusId;
    }

    private function getStatusIdByName(string $name): string {
        $stmt = $this->pdo->prepare('SELECT id FROM statuses WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $statusId = (string) ($stmt->fetchColumn() ?: '');
        if ($statusId === '') {
            throw new \RuntimeException('Invalid status selected', 422);
        }
        return $statusId;
    }

    private function getDefaultStageId(): string {
        $stmt = $this->pdo->query(
            "SELECT id FROM stages WHERE name = 'Logged' AND is_active = 1 LIMIT 1"
        );
        $stageId = (string) ($stmt->fetchColumn() ?: '');
        if ($stageId === '') {
            $stmt = $this->pdo->query(
                'SELECT id FROM stages WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 1'
            );
            $stageId = (string) ($stmt->fetchColumn() ?: '');
        }
        if ($stageId === '') {
            throw new \RuntimeException('No active stages configured', 500);
        }
        return $stageId;
    }

    private function getStageIdByName(string $name): string {
        $stmt = $this->pdo->prepare('SELECT id FROM stages WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $stageId = (string) ($stmt->fetchColumn() ?: '');
        if ($stageId === '') {
            throw new \RuntimeException('Invalid stage selected', 422);
        }
        return $stageId;
    }

    public function getCategoryIdByName(string $name): string {
        $stmt = $this->pdo->prepare('SELECT id FROM categories WHERE name = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$name]);
        $categoryId = (string) ($stmt->fetchColumn() ?: '');
        if ($categoryId === '') {
            throw new \RuntimeException('Invalid category selected', 422);
        }
        return $categoryId;
    }

    
    public function createReport(string $reference, string $categoryId, ?string $categoryOther, string $description): string {
        $defaultStatusId = $this->getDefaultStatusId();
        $defaultStageId  = $this->getDefaultStageId();
        $id = self::generateUuid();

        $stmt = $this->pdo->prepare(
            'INSERT INTO feedbacks (id, reference_no, category_id, category_other, description, status_id, stage_id, priority, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );

        $stmt->execute([
            $id,
            $reference,
            $categoryId,
            $categoryOther,
            $description,
            $defaultStatusId,
            $defaultStageId,
            'Normal',
        ]);

        return $id;
    }

    
    public function findByReference(string $reference): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, s.name AS status,
                    COALESCE(r.category_other, c.name) AS category,
                    st.name AS stage
             FROM feedbacks r
             LEFT JOIN statuses s  ON s.id  = r.status_id
             LEFT JOIN categories c ON c.id = r.category_id
             LEFT JOIN stages st   ON st.id = r.stage_id
             WHERE r.reference_no = ?'
        );
        $stmt->execute([$reference]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    
    public function getDetailedReport(string $reference): ?array {
        $report = $this->findByReference($reference);

        if (!$report) {
            return null;
        }

        return [
            'report'      => $report,
            'updates'     => $this->getReportUpdates((string) $report['id']),
            'attachments' => $this->getReportAttachments((string) $report['id']),
            'audit'       => $this->getReportAudit($reference),
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

    
    public function listCases(array $filters = []): array {
        $params = [];
        $query = 'SELECT r.*, s.name AS status,
                         COALESCE(r.category_other, c.name) AS category,
                         st.name AS stage
                  FROM feedbacks r
                  LEFT JOIN statuses s   ON s.id  = r.status_id
                  LEFT JOIN categories c ON c.id  = r.category_id
                  LEFT JOIN stages st    ON st.id = r.stage_id';
        $query .= $this->buildCaseWhereClause($filters, $params);

        $allowedSortBy = ['created_at', 'category', 'status', 'stage', 'reference_no', 'priority'];
        $sortBy = in_array($filters['sort_by'] ?? 'created_at', $allowedSortBy, true)
            ? (string) $filters['sort_by']
            : 'created_at';
        $sortOrder = strtoupper((string) ($filters['sort_order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $sortColumnMap = [
            'created_at'   => 'r.created_at',
            'category'     => 'c.name',
            'status'       => 's.name',
            'stage'        => 'st.name',
            'reference_no' => 'r.reference_no',
            'priority'     => 'r.priority',
        ];
        $query .= ' ORDER BY ' . ($sortColumnMap[$sortBy] ?? 'r.created_at') . ' ' . $sortOrder;

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countCases(array $filters = []): int {
        $params = [];
        $query = 'SELECT COUNT(*)
                  FROM feedbacks r
                  LEFT JOIN statuses s   ON s.id  = r.status_id
                  LEFT JOIN categories c ON c.id  = r.category_id
                  LEFT JOIN stages st    ON st.id = r.stage_id';
        $query .= $this->buildCaseWhereClause($filters, $params);

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function listCasesPaged(array $filters = [], int $page = 1, int $perPage = 10): array {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $params = [];
        $query = 'SELECT r.*, s.name AS status,
                         COALESCE(r.category_other, c.name) AS category,
                         st.name AS stage
                  FROM feedbacks r
                  LEFT JOIN statuses s   ON s.id  = r.status_id
                  LEFT JOIN categories c ON c.id  = r.category_id
                  LEFT JOIN stages st    ON st.id = r.stage_id';
        $query .= $this->buildCaseWhereClause($filters, $params);

        $allowedSortBy = ['created_at', 'category', 'status', 'stage', 'reference_no', 'priority'];
        $sortBy = in_array($filters['sort_by'] ?? 'created_at', $allowedSortBy, true)
            ? (string) $filters['sort_by']
            : 'created_at';
        $sortOrder = strtoupper((string) ($filters['sort_order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $sortColumnMap = [
            'created_at'   => 'r.created_at',
            'category'     => 'c.name',
            'status'       => 's.name',
            'stage'        => 'st.name',
            'reference_no' => 'r.reference_no',
            'priority'     => 'r.priority',
        ];
        $query .= ' ORDER BY ' . ($sortColumnMap[$sortBy] ?? 'r.created_at') . ' ' . $sortOrder;
        $query .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    public function listPublicReports(array $filters = []): array {
        $query = 'SELECT r.reference_no, COALESCE(r.category_other, c.name) AS category,
                         s.name AS status, r.anonymized_summary, r.outcome_comments, r.created_at
                  FROM feedbacks r
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

    
    public function updateReport(string $reference, array $data, ?string $updatedByUserId = null): bool {
        $allowed = ['priority', 'stage', 'status', 'anonymized_summary', 'action_taken',
                    'outcome_comments', 'internal_notes', 'acknowledged_at'];

        $updates = [];
        $params  = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                if ($key === 'status') {
                    $updates[] = 'status_id = ?';
                    $params[]  = $this->getStatusIdByName((string) $value);
                } elseif ($key === 'stage') {
                    $updates[] = 'stage_id = ?';
                    $params[]  = $this->getStageIdByName((string) $value);
                } else {
                    $updates[] = "$key = ?";
                    $params[]  = $value;
                }
            }
        }

        if (empty($updates)) {
            return false;
        }

        $updates[] = 'updated_by_user_id = ?';
        $params[] = $updatedByUserId;

        $params[] = $reference;
        $query = 'UPDATE feedbacks SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE reference_no = ?';

        $stmt = $this->pdo->prepare($query);
        return $stmt->execute($params);
    }

    
    public function createUpdate(string $feedbackId, string $updateReference, string $updateText): string {
        $id = self::generateUuid();

        $stmt = $this->pdo->prepare(
            'INSERT INTO report_updates (id, feedback_id, update_reference_no, update_text, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );

        $stmt->execute([$id, $feedbackId, $updateReference, $updateText]);
        return $id;
    }

    
    public function getReportUpdates(string $feedbackId): array {
        $stmt = $this->pdo->prepare('SELECT * FROM report_updates WHERE feedback_id = ? ORDER BY created_at ASC');
        $stmt->execute([$feedbackId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    public function saveAttachment(string $feedbackId, ?string $updateId, string $originalName,
                                   string $storedName, string $mimeType, int $size): string {
        $id = self::generateUuid();

        $stmt = $this->pdo->prepare(
            'INSERT INTO attachments (id, feedback_id, report_update_id, original_name, stored_name, mime_type, size_bytes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        $stmt->execute([$id, $feedbackId, $updateId, $originalName, $storedName, $mimeType, $size]);
        return $id;
    }

    
    public function getReportAttachments(string $feedbackId): array {
        $stmt = $this->pdo->prepare('SELECT * FROM attachments WHERE feedback_id = ? ORDER BY created_at DESC');
        $stmt->execute([$feedbackId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    public function getAttachmentById(string $id): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM attachments WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    
    public function logAudit(string $actor, string $action, string $reference, string $details, ?string $actorUserId = null): string {
        $feedbackIdStmt = $this->pdo->prepare('SELECT id FROM feedbacks WHERE reference_no = ? LIMIT 1');
        $feedbackIdStmt->execute([$reference]);
        $feedbackId = ($feedbackIdStmt->fetchColumn() ?: null);

        $id = self::generateUuid();

        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (id, feedback_id, actor, actor_user_id, action, reference_no, details, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        $stmt->execute([$id, $feedbackId, $actor, $actorUserId, $action, $reference, $details]);
        return $id;
    }

    
    public function pruneOldAuditLogs(int $retentionDays = 1825): int {
        $stmt = $this->pdo->prepare(
            'DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
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
             VALUES (?, ?, ?, ?, NOW())'
        );

        $stmt->execute([$id, $feedbackId, $kind, $recipient]);
        return $id;
    }

    
    public function getRecipientsByRole(string $role): array {
        $stmt = $this->pdo->prepare('SELECT email FROM users WHERE role = ? AND is_active = 1');
        $stmt->execute([$role]);
        return array_map(
            static fn (array $row): string => (string) $row['email'],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    
    public function getUnacknowledgedReportsNeedingNotification(int $hours, string $kind): array {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.reference_no, COALESCE(r.category_other, c.name) AS category, r.created_at
             FROM feedbacks r
             LEFT JOIN categories c ON c.id = r.category_id
             WHERE r.acknowledged_at IS NULL
               AND TIMESTAMPDIFF(HOUR, r.created_at, NOW()) >= ?
               AND NOT EXISTS (
                   SELECT 1 FROM notifications n
                   WHERE n.feedback_id = r.id AND n.kind = ?
               )'
        );

        $stmt->execute([$hours, $kind]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    public function getQuarterlyCategoryTrends(): array {
        $stmt = $this->pdo->query(
            'SELECT YEAR(r.created_at) AS year_no,
                    QUARTER(r.created_at) AS quarter_no,
                    c.name AS category,
                    COUNT(*) AS total_cases
             FROM feedbacks r
             LEFT JOIN categories c ON c.id = r.category_id
             GROUP BY YEAR(r.created_at), QUARTER(r.created_at), c.name
             ORDER BY year_no DESC, quarter_no DESC, c.name ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    public function getStatusTotals(): array {
        $stmt = $this->pdo->query(
            'SELECT s.name AS status, COUNT(*) AS total
             FROM feedbacks r
             LEFT JOIN statuses s ON s.id = r.status_id
             GROUP BY s.name
             ORDER BY total DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    public function getCategoryFrequencySummary(): array {
        $stmt = $this->pdo->query(
            'SELECT c.name AS category,
                    COUNT(*) AS total_cases,
                    SUM(CASE WHEN s.name NOT LIKE \'%completed%\' THEN 1 ELSE 0 END) AS open_cases
             FROM feedbacks r
             LEFT JOIN statuses s ON s.id = r.status_id
             LEFT JOIN categories c ON c.id = r.category_id
             GROUP BY c.name
             ORDER BY open_cases DESC, total_cases DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
