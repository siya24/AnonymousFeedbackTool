<?php declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Exceptions\ServerException;
use App\Exceptions\ValidationException;
use App\Repositories\AttachmentRepository;
use App\Repositories\AuditRepository;
use App\Repositories\FeedbackInsightsRepository;
use App\Repositories\FeedbackRepository;
use DateTime;

class FeedbackService {
    private const DATETIME_FORMAT     = 'Y-m-d H:i:s';
    private const MSG_FEEDBACK_FOUND  = 'Feedback case not found';
    private const MIME_ZIP            = 'application/zip';

    public function __construct(
        private FeedbackRepository $repository,
        private FeedbackInsightsRepository $insightsRepository,
        private AttachmentRepository $attachmentRepository,
        private AuditRepository $auditRepository,
        private NotificationService $notificationService,
        private ?MalwareScannerInterface $malwareScanner = null,
        private ?string $attachmentsStoragePath = null
    ) {
        $this->malwareScanner = $malwareScanner ?? new NoOpMalwareScanner();
        $defaultStoragePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'anonymous_feedback_private_uploads';
        $configuredPath = trim((string) ($this->attachmentsStoragePath ?? ''));
        $this->attachmentsStoragePath = $configuredPath !== '' ? rtrim($configuredPath, "\\/") : $defaultStoragePath;
    }

    private function ensureAttachmentsDirectory(): string
    {
        $uploadDir = (string) $this->attachmentsStoragePath;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true)) {
            throw new ServerException('Failed to initialize internal upload directory');
        }

        $resolved = realpath($uploadDir);
        if ($resolved === false) {
            throw new ServerException('Unable to resolve internal upload directory');
        }

        return $resolved;
    }

    
    public function generateReference(string $prefix = 'AF'): string {
        $date = (new DateTime())->format('Ymd');
        $random = strtoupper(bin2hex(random_bytes(3)));
        return "{$prefix}-{$date}-{$random}";
    }

    
    public function submitFeedback(string $category, string $description, ?string $categoryOther = null): array {
        $reference = $this->generateReference('AF');

        $categoryId = $this->repository->getCategoryIdByName($category);
        $normalizedOther = ($category === 'Other' && $categoryOther !== null && $categoryOther !== '')
            ? $categoryOther
            : null;

        $feedbackId = $this->repository->createReport($reference, $categoryId, $normalizedOther, $description);

        
        $this->auditRepository->logAudit('anonymous', 'feedback_submitted', $reference,
            "New feedback submitted in category: {$category}");

        return [
            'success' => true,
            'reference' => $reference,
            'feedback_id' => $feedbackId,
            'report_id' => $feedbackId,
            'message' => 'Feedback submitted successfully'
        ];
    }

    
    public function submitFollowUp(string $reference, string $updateText): array {
        $report = $this->repository->findByReference($reference);
        
        if (!$report) {
            throw new NotFoundException(self::MSG_FEEDBACK_FOUND);
        }

        $updateReference = $this->generateReference('UPD');
        $updateId = $this->repository->createUpdate((string)$report['id'], $updateReference, $updateText);
        
        
        $this->auditRepository->logAudit('anonymous', 'followup_submitted', $reference,
            "Follow-up submitted: {$updateReference}");

        return [
            'success' => true,
            'update_reference' => $updateReference,
            'update_id' => $updateId,
            'message' => 'Follow-up submitted successfully'
        ];
    }

    
    public function getCaseDetails(string $reference): array {
        $detailed = $this->repository->getDetailedReport($reference);
        
        if (!$detailed) {
            throw new NotFoundException(self::MSG_FEEDBACK_FOUND);
        }

        return $detailed;
    }

    
    public function getPublicReports(array $filters = []): array {
        return $this->repository->listPublicReports($filters);
    }

    
    public function listCasesForHr(array $filters = [], int $page = 1, int $perPage = 10): array {
        $total = $this->repository->countCases($filters);
        $items = $this->repository->listCasesPaged($filters, $page, $perPage);

        return [
            'items' => $items,
            'total' => $total,
            'page' => max(1, $page),
            'per_page' => max(1, min(100, $perPage)),
            'total_pages' => (int) max(1, ceil($total / max(1, $perPage))),
        ];
    }

    
    public function getDashboardTrends(): array {
        return [
            'quarterly_by_category' => $this->insightsRepository->getQuarterlyCategoryTrends(),
            'status_totals'         => $this->insightsRepository->getStatusTotals(),
            'province_totals'       => $this->insightsRepository->getProvinceTotals(),
            'category_frequency'    => $this->insightsRepository->getCategoryFrequencySummary(),
        ];
    }

    public function listAssignablePersonnel(): array
    {
        return $this->insightsRepository->listAssignablePersonnel();
    }

    public function listAssignableRoles(): array
    {
        return $this->insightsRepository->listAssignableRoles();
    }

    
    public function processScheduledNotifications(): array {
        
        $pruned = $this->auditRepository->pruneOldAuditLogs(1825);

        $result = $this->notificationService->processScheduledNotifications();
        $result['audit_logs_pruned'] = $pruned;
        return $result;
    }

    
    public function updateCaseForHr(string $reference, array $updateData, string $hrUserId, string $hrUserName = 'HR user'): array {
        $report = $this->repository->findByReference($reference);
        
        if (!$report) {
            throw new NotFoundException(self::MSG_FEEDBACK_FOUND);
        }

        $acknowledge = !empty($updateData['acknowledge']);
        if (!$acknowledge) {
            throw new ValidationException('Acknowledge Case is required before saving', 400);
        }

        $updateData = $this->normalizeReporterCommunicationFields($updateData);

        
        if ($updateData['acknowledge'] ?? false) {
            $updateData['acknowledged_at'] = date(self::DATETIME_FORMAT);
            unset($updateData['acknowledge']);
        } else {
            unset($updateData['acknowledge']);
        }

        $assignmentChanged = false;
        $isReassignment = false;
        $wasUnassigned = false;

        if (array_key_exists('assigned_to_user_id', $updateData)) {
            ['data' => $updateData, 'changed' => $assignmentChanged, 'reassigned' => $isReassignment, 'unassigned' => $wasUnassigned]
                = $this->resolveUserAssignment($updateData, $report);
        }

        if (array_key_exists('assigned_role_id', $updateData)) {
            $updateData = $this->resolveRoleAssignment($updateData, $report);
        }

        
        $this->repository->updateReport($reference, $updateData, $hrUserId);
        
        
        $details = json_encode($updateData);
        $this->auditRepository->logAudit("hr:{$hrUserId}", 'case_updated', $reference, $details, $hrUserId);

        if ($assignmentChanged) {
            $updated = $this->repository->findByReference($reference);
            if ($updated && !empty($updated['assigned_to_email'])) {
                $this->notificationService->notifyCaseAssigned(
                    (string) $report['id'],
                    $reference,
                    (string) ($updated['category'] ?? $report['category'] ?? ''),
                    (string) $updated['assigned_to_email'],
                    (string) ($updated['assigned_to_name'] ?? 'Assigned investigator'),
                    $hrUserName,
                    $isReassignment
                );
            }
        }

        if ($wasUnassigned && !empty($report['assigned_to_email'])) {
            $this->notificationService->notifyCaseUnassigned(
                (string) $report['id'],
                $reference,
                (string) ($report['category'] ?? ''),
                (string) $report['assigned_to_email'],
                (string) ($report['assigned_to_name'] ?? 'Assigned investigator'),
                $hrUserName
            );
        }
        
        return [
            'success' => true,
            'reference' => $reference,
            'message' => 'Case updated successfully'
        ];
    }

    private function resolveUserAssignment(array $updateData, array $report): array
    {
        $incomingAssignee = trim((string) ($updateData['assigned_to_user_id'] ?? ''));
        $existingAssignee = (string) ($report['assigned_to_user_id'] ?? '');
        $updateData['assigned_to_user_id'] = $incomingAssignee !== '' ? $incomingAssignee : null;
        $changed    = false;
        $reassigned = false;
        $unassigned = false;

        if ($incomingAssignee !== '') {
            $updateData['assigned_role_id'] = null;
        }

        if ($incomingAssignee !== '' && $incomingAssignee !== $existingAssignee) {
            $updateData['assigned_at'] = date(self::DATETIME_FORMAT);
            $changed    = true;
            $reassigned = ($existingAssignee !== '');
        }

        if ($incomingAssignee === '' && $existingAssignee !== '') {
            $updateData['assigned_at'] = null;
            $unassigned = true;
        }

        return ['data' => $updateData, 'changed' => $changed, 'reassigned' => $reassigned, 'unassigned' => $unassigned];
    }

    private function normalizeReporterCommunicationFields(array $updateData): array
    {
        $statusValue = strtolower(trim((string) ($updateData['status'] ?? '')));
        $isCompletedStatus = str_contains($statusValue, 'completed');
        $stageValue = strtolower(trim((string) ($updateData['stage'] ?? '')));
        $isClosedStage = str_contains($stageValue, 'closed');

        $anonymizedSummary = trim((string) ($updateData['anonymized_summary'] ?? ''));
        $reporterFeedback = trim((string) ($updateData['reporter_feedback'] ?? ''));
        $actionTaken = trim((string) ($updateData['action_taken'] ?? ''));
        $outcomeComments = trim((string) ($updateData['outcome_comments'] ?? ''));

        if ($anonymizedSummary === '') {
            throw new ValidationException('Anonymized Summary is required', 400);
        }

        if ($isCompletedStatus) {
            $missingFields = array_keys(array_filter([
                'Feedback to Reporter' => $reporterFeedback === '',
                'Action Taken' => $actionTaken === '',
                'Outcome Comments' => $isClosedStage && $outcomeComments === '',
            ]));
            if ($missingFields !== []) {
                throw new ValidationException(
                    implode(', ', $missingFields) . ' are required when case status is completed' . ($isClosedStage ? ' and the stage is closed' : ''),
                    400
                );
            }
        }

        if (array_key_exists('anonymized_summary', $updateData)) {
            $updateData['anonymized_summary'] = $anonymizedSummary;
        }

        if (array_key_exists('reporter_feedback', $updateData)) {
            $updateData['reporter_feedback'] = $reporterFeedback;
        }

        if (array_key_exists('action_taken', $updateData)) {
            $updateData['action_taken'] = $actionTaken;
        }

        if (array_key_exists('outcome_comments', $updateData)) {
            $updateData['outcome_comments'] = $outcomeComments;
        }

        return $updateData;
    }

    private function resolveRoleAssignment(array $updateData, array $report): array
    {
        $incomingRole = trim((string) ($updateData['assigned_role_id'] ?? ''));
        $existingRole = (string) ($report['assigned_role_id'] ?? '');
        $updateData['assigned_role_id'] = $incomingRole !== '' ? $incomingRole : null;

        if ($incomingRole !== '') {
            $updateData['assigned_to_user_id'] = null;
            if ($incomingRole !== $existingRole) {
                $updateData['assigned_at'] = date(self::DATETIME_FORMAT);
            }
        } elseif ($existingRole !== '' && empty($updateData['assigned_to_user_id'])) {
            $updateData['assigned_at'] = null;
        }

        return $updateData;
    }

    
    public function storeAttachments(string $feedbackId, ?string $updateId, array $files): array {
        $stored = [];
        $allowed = [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt',
            'jpg', 'jpeg', 'png', 'gif',
            'mp3', 'wav', 'm4a',
            'mp4', 'webm', 'mov',
            'zip', 'rar', '7z'
        ];
        $maxSize = 25 * 1024 * 1024;
        $uploadDir = $this->ensureAttachmentsDirectory();
        $mimeMap   = $this->buildMimeMap();

        foreach ($files['name'] ?? [] as $index => $name) {
            $error   = $files['error'][$index] ?? null;
            $tmpName = $files['tmp_name'][$index] ?? null;
            $size    = $files['size'][$index] ?? 0;

            if ($error !== UPLOAD_ERR_OK || !$tmpName) {
                continue;
            }

            if ($size > $maxSize) {
                throw new ValidationException("File {$name} exceeds maximum size", 400);
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                throw new ValidationException("File type {$ext} not allowed", 400);
            }

            $this->validateMimeType($name, $ext, $tmpName, $mimeMap);

            $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
            $uploadPath = $uploadDir . DIRECTORY_SEPARATOR . $storedName;

            if (!move_uploaded_file($tmpName, $uploadPath)) {
                throw new ServerException("Failed to store file {$name}");
            }

            $this->scanUploadedFile($name, $uploadPath);

            $mimeType = $mimeMap[$ext][0] ?? 'application/octet-stream';
            $attachmentId = $this->attachmentRepository->saveAttachment(
                $feedbackId,
                $updateId,
                $name,
                $storedName,
                $mimeType,
                $size
            );

            $stored[] = [
                'id'          => $attachmentId,
                'name'        => $name,
                'stored_name' => $storedName,
                'size'        => $size,
            ];
        }

        return $stored;
    }

    private function buildMimeMap(): array
    {
        return [
            'pdf'  => ['application/pdf'],
            'doc'  => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', self::MIME_ZIP],
            'xls'  => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', self::MIME_ZIP],
            'ppt'  => ['application/vnd.ms-powerpoint'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', self::MIME_ZIP],
            'csv'  => ['text/csv', 'text/plain', 'application/vnd.ms-excel'],
            'txt'  => ['text/plain'],
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'mp3'  => ['audio/mpeg', 'audio/mp3'],
            'wav'  => ['audio/wav', 'audio/x-wav'],
            'm4a'  => ['audio/mp4', 'audio/x-m4a'],
            'mp4'  => ['video/mp4'],
            'webm' => ['video/webm'],
            'mov'  => ['video/quicktime'],
            'zip'  => [self::MIME_ZIP, 'application/x-zip-compressed'],
            'rar'  => ['application/vnd.rar', 'application/x-rar-compressed'],
            '7z'   => ['application/x-7z-compressed'],
        ];
    }

    private function validateMimeType(string $name, string $ext, string $tmpName, array $mimeMap): void
    {
        if (!function_exists('finfo_open')) {
            return;
        }

        $finfo        = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);
        $allowedMimes = $mimeMap[$ext] ?? [];

        if (!empty($allowedMimes) && !in_array($detectedMime, $allowedMimes, true)) {
            throw new ValidationException(
                "File {$name} content type ({$detectedMime}) does not match its declared extension",
                400
            );
        }
    }

    private function scanUploadedFile(string $name, string $uploadPath): void
    {
        try {
            if (!$this->malwareScanner->scan($uploadPath)) {
                @unlink($uploadPath);
                throw new ValidationException(
                    "File {$name} failed malware scan and was rejected. Contact support if you believe this is an error.",
                    422
                );
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            // For other scanner errors (network, etc.), log but allow file to proceed
            error_log("Malware scan warning for {$name}: " . $e->getMessage());
        }
    }
}



