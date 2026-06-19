<?php declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\NotFoundException;
use App\Exceptions\ServerException;
use PDO;

class FeedbackRepository {
    public function __construct(
        private PDO $pdo,
        private ?AuditRepository $auditRepository = null,
        private ?CoInvestigatorRepository $coInvestigatorRepository = null,
        private ?AttachmentRepository $attachmentRepository = null,
    ) {}

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

    private function getDefaultStatusId(): string {
        $stmt = $this->pdo->query(
            'SELECT TOP 1 id FROM statuses WHERE is_active = 1 ORDER BY sort_order ASC, name ASC'
        );
        $statusId = (string) ($stmt->fetchColumn() ?: '');
        if ($statusId === '') {
            throw new ServerException('No active statuses configured');
        }
        return $statusId;
    }

    private function getStatusIdByName(string $name): string {
        $stmt = $this->pdo->prepare('SELECT TOP 1 id FROM statuses WHERE name = ?');
        $stmt->execute([$name]);
        $statusId = (string) ($stmt->fetchColumn() ?: '');
        if ($statusId === '') {
            throw new NotFoundException('Invalid status selected');
        }
        return $statusId;
    }

    private function getDefaultStageId(): string {
        $stmt = $this->pdo->query(
            "SELECT TOP 1 id FROM stages WHERE name = 'Logged' AND is_active = 1"
        );
        $stageId = (string) ($stmt->fetchColumn() ?: '');
        if ($stageId === '') {
            $stmt = $this->pdo->query(
                'SELECT TOP 1 id FROM stages WHERE is_active = 1 ORDER BY sort_order ASC, name ASC'
            );
            $stageId = (string) ($stmt->fetchColumn() ?: '');
        }
        if ($stageId === '') {
            throw new ServerException('No active stages configured');
        }
        return $stageId;
    }

    private function getStageIdByName(string $name): string {
        $stmt = $this->pdo->prepare('SELECT TOP 1 id FROM stages WHERE name = ?');
        $stmt->execute([$name]);
        $stageId = (string) ($stmt->fetchColumn() ?: '');
        if ($stageId === '') {
            throw new NotFoundException('Invalid stage selected');
        }
        return $stageId;
    }

    private function getProvinceIdByName(string $name): string {
        $stmt = $this->pdo->prepare('SELECT TOP 1 id FROM provinces WHERE name = ? AND is_active = 1');
        $stmt->execute([$name]);
        $provinceId = (string) ($stmt->fetchColumn() ?: '');
        if ($provinceId === '') {
            throw new NotFoundException('Invalid province selected');
        }
        return $provinceId;
    }

    public function getCategoryIdByName(string $name): string {
        $stmt = $this->pdo->prepare('SELECT TOP 1 id FROM categories WHERE name = ? AND is_active = 1');
        $stmt->execute([$name]);
        $categoryId = (string) ($stmt->fetchColumn() ?: '');
        if ($categoryId === '') {
            throw new NotFoundException('Invalid category selected');
        }
        return $categoryId;
    }

    
    public function createReport(string $reference, string $categoryId, ?string $categoryOther, string $description): string {
        $defaultStatusId = $this->getDefaultStatusId();
        $defaultStageId  = $this->getDefaultStageId();
        $id = self::generateUuid();

        $stmt = $this->pdo->prepare(
            'INSERT INTO feedbacks (id, reference_no, category_id, category_other, description, status_id, stage_id, priority, created_at, updated_at)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
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
                    st.name AS stage,
                      p.name AS province,
                    ar.name AS assigned_role_name,
                    assignee.name AS assigned_to_name,
                    assignee.email AS assigned_to_email
             FROM feedbacks r
             LEFT JOIN statuses s  ON s.id  = r.status_id
             LEFT JOIN categories c ON c.id = r.category_id
             LEFT JOIN stages st   ON st.id = r.stage_id
                  LEFT JOIN provinces p ON p.id = r.province_id
             LEFT JOIN assignment_roles ar ON ar.id = r.assigned_role_id
             LEFT JOIN users assignee ON assignee.id = r.assigned_to_user_id
             WHERE r.reference_no = ?'
        );
        $stmt->execute([$reference]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findById(string $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, s.name AS status,
                    COALESCE(r.category_other, c.name) AS category,
                    st.name AS stage,
                      p.name AS province,
                    ar.name AS assigned_role_name,
                    assignee.name AS assigned_to_name,
                    assignee.email AS assigned_to_email
             FROM feedbacks r
             LEFT JOIN statuses s  ON s.id  = r.status_id
             LEFT JOIN categories c ON c.id = r.category_id
             LEFT JOIN stages st   ON st.id = r.stage_id
                  LEFT JOIN provinces p ON p.id = r.province_id
             LEFT JOIN assignment_roles ar ON ar.id = r.assigned_role_id
             LEFT JOIN users assignee ON assignee.id = r.assigned_to_user_id
             WHERE r.id = ?'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    
    public function getDetailedReport(string $reference): ?array {
        $report = $this->findByReference($reference);

        if (!$report) {
            return null;
        }

        return [
            'report'           => $report,
            'updates'          => $this->getReportUpdates((string) $report['id']),
            'attachments'      => $this->attachmentRepository ? $this->attachmentRepository->getReportAttachments((string) $report['id']) : [],
            'audit'            => $this->auditRepository ? $this->auditRepository->getReportAudit($reference) : [],
            'co_investigators' => $this->coInvestigatorRepository ? $this->coInvestigatorRepository->getCoInvestigators((string) $report['id']) : [],
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
            $where .= ' AND CAST(r.created_at AS DATE) = ?';
            $params[] = $filters['date'];
        }

        return $where;
    }

    
    public function listCases(array $filters = []): array {
        $params = [];
        $query = 'SELECT r.*, s.name AS status,
                         COALESCE(r.category_other, c.name) AS category,
                         st.name AS stage,
                    p.name AS province,
                         ar.name AS assigned_role_name,
                         assignee.name AS assigned_to_name,
                         assignee.email AS assigned_to_email
                  FROM feedbacks r
                  LEFT JOIN statuses s   ON s.id  = r.status_id
                  LEFT JOIN categories c ON c.id  = r.category_id
                  LEFT JOIN stages st    ON st.id = r.stage_id
                LEFT JOIN provinces p  ON p.id  = r.province_id
                  LEFT JOIN assignment_roles ar ON ar.id = r.assigned_role_id
                  LEFT JOIN users assignee ON assignee.id = r.assigned_to_user_id';
        $query .= $this->buildCaseWhereClause($filters, $params);

        $allowedSortBy = ['created_at', 'category', 'status', 'stage', 'reference_no', 'priority', 'assigned_to', 'assigned_role'];
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
            'assigned_to'  => 'assignee.name',
            'assigned_role'=> 'ar.name',
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
                    st.name AS stage,
                                        p.name AS province,
                                        ar.name AS assigned_role_name,
                    assignee.name AS assigned_to_name,
                    assignee.email AS assigned_to_email
                  FROM feedbacks r
                  LEFT JOIN statuses s   ON s.id  = r.status_id
                  LEFT JOIN categories c ON c.id  = r.category_id
                LEFT JOIN stages st    ON st.id = r.stage_id
                                LEFT JOIN provinces p  ON p.id = r.province_id
                                LEFT JOIN assignment_roles ar ON ar.id = r.assigned_role_id
                LEFT JOIN users assignee ON assignee.id = r.assigned_to_user_id';
        $query .= $this->buildCaseWhereClause($filters, $params);

                 $allowedSortBy = ['created_at', 'category', 'status', 'stage', 'reference_no', 'priority', 'assigned_to', 'assigned_role'];
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
            'assigned_to'  => 'assignee.name',
            'assigned_role'=> 'ar.name',
        ];
        $query .= ' ORDER BY ' . ($sortColumnMap[$sortBy] ?? 'r.created_at') . ' ' . $sortOrder;
        $query .= ' OFFSET ' . (int) $offset . ' ROWS FETCH NEXT ' . (int) $perPage . ' ROWS ONLY';

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
            $query .= ' AND CAST(r.created_at AS DATE) = ?';
            $params[] = $filters['date'];
        }

        $query .= ' ORDER BY r.created_at DESC';

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    
    public function updateReport(string $reference, array $data, ?string $updatedByUserId = null): bool {
        $allowed = ['priority', 'stage', 'status', 'province', 'anonymized_summary', 'reporter_feedback', 'action_taken',
                    'outcome_comments', 'internal_notes', 'acknowledged_at', 'assigned_to_user_id', 'assigned_role_id', 'assigned_at'];
        $lookups = ['status' => 'status_id', 'stage' => 'stage_id', 'province' => 'province_id'];
        $lookupMethods = ['status' => 'getStatusIdByName', 'stage' => 'getStageIdByName', 'province' => 'getProvinceIdByName'];
        $nullable = ['assigned_to_user_id', 'assigned_role_id'];

        $updates = [];
        $params  = [];

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }

            if (isset($lookups[$key])) {
                $column = $lookups[$key];
                $method = $lookupMethods[$key];

                if ($key === 'province' && ($value === '' || $value === null)) {
                    $updates[] = "{$column} = ?";
                    $params[] = null;
                    continue;
                }

                $updates[] = "{$column} = ?";
                $params[] = $this->$method((string) $value);
                continue;
            }

            $resolvedValue = (in_array($key, $nullable, true) && ($value === '' || $value === null))
                ? null
                : $value;

            $updates[] = "{$key} = ?";
            $params[]  = $resolvedValue;
        }

        if (empty($updates)) {
            return false;
        }

        $updates[] = 'updated_by_user_id = ?';
        $params[]  = $updatedByUserId;
        $params[]  = $reference;
        $query = 'UPDATE feedbacks SET ' . implode(', ', $updates) . ', updated_at = CURRENT_TIMESTAMP WHERE reference_no = ?';

        $stmt = $this->pdo->prepare($query);
        return $stmt->execute($params);
    }


    
    public function createUpdate(string $feedbackId, string $updateReference, string $updateText): string {
        $id = self::generateUuid();

        $stmt = $this->pdo->prepare(
            'INSERT INTO report_updates (id, feedback_id, update_reference_no, update_text, created_at)
               VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)'
        );

        $stmt->execute([$id, $feedbackId, $updateReference, $updateText]);
        return $id;
    }

    
    public function getReportUpdates(string $feedbackId): array {
        $stmt = $this->pdo->prepare('SELECT * FROM report_updates WHERE feedback_id = ? ORDER BY created_at ASC');
        $stmt->execute([$feedbackId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function logAudit(
        string $actor,
        string $action,
        string $reference,
        string $details,
        ?string $actorUserId = null
    ): string {
        if ($this->auditRepository === null) {
            throw new ServerException('Audit repository unavailable');
        }

        return $this->auditRepository->logAudit($actor, $action, $reference, $details, $actorUserId);
    }

    

    // ========== Co-Investigator Methods — delegated to CoInvestigatorRepository ==========
    // (moved to App\Repositories\CoInvestigatorRepository)
}

