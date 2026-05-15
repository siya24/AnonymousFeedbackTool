<?php declare(strict_types=1);

namespace App\Services;

use App\Repositories\FeedbackRepository;
use DateTime;

class FeedbackService {
    public function __construct(
        private FeedbackRepository $repository,
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
            throw new \RuntimeException('Failed to initialize internal upload directory', 500);
        }

        $resolved = realpath($uploadDir);
        if ($resolved === false) {
            throw new \RuntimeException('Unable to resolve internal upload directory', 500);
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

        
        $this->repository->logAudit('anonymous', 'feedback_submitted', $reference,
            "New feedback submitted in category: {$category}");

        
        $this->notificationService->notifyNewFeedback($feedbackId, $reference, $category);

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
            throw new \RuntimeException('Feedback case not found', 404);
        }

        $updateReference = $this->generateReference('UPD');
        $updateId = $this->repository->createUpdate((string)$report['id'], $updateReference, $updateText);
        
        
        $this->repository->logAudit('anonymous', 'followup_submitted', $reference,
            "Follow-up submitted: {$updateReference}");

        
        $this->notificationService->notifyFollowUpSubmitted(
            (string)$report['id'],
            $reference,
            (string)($report['category'] ?? '')
        );

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
            throw new \RuntimeException('Feedback case not found', 404);
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
            'quarterly_by_category' => $this->repository->getQuarterlyCategoryTrends(),
            'status_totals'         => $this->repository->getStatusTotals(),
            'category_frequency'    => $this->repository->getCategoryFrequencySummary(),
        ];
    }

    public function listAssignablePersonnel(): array
    {
        return $this->repository->listAssignablePersonnel();
    }

    public function listAssignableRoles(): array
    {
        return $this->repository->listAssignableRoles();
    }

    
    public function processScheduledNotifications(): array {
        
        $pruned = $this->repository->pruneOldAuditLogs(1825);

        $result = $this->notificationService->processScheduledNotifications();
        $result['audit_logs_pruned'] = $pruned;
        return $result;
    }

    
    public function updateCaseForHr(string $reference, array $updateData, string $hrUserId, string $hrUserName = 'HR user'): array {
        $report = $this->repository->findByReference($reference);
        
        if (!$report) {
            throw new \RuntimeException('Feedback case not found', 404);
        }

        
        if ($updateData['status'] === 'Investigation completed' && empty($updateData['outcome_comments'])) {
            throw new \RuntimeException('Outcome comments required when marking as completed', 400);
        }

        
        if ($updateData['acknowledge'] ?? false) {
            $updateData['acknowledged_at'] = date('Y-m-d H:i:s');
            unset($updateData['acknowledge']);
        } else {
            unset($updateData['acknowledge']);
        }

        $assignmentChanged = false;
        $isReassignment = false;
        $wasUnassigned = false;

        if (array_key_exists('assigned_to_user_id', $updateData)) {
            $incomingAssignee = trim((string) ($updateData['assigned_to_user_id'] ?? ''));
            $existingAssignee = (string) ($report['assigned_to_user_id'] ?? '');
            $updateData['assigned_to_user_id'] = $incomingAssignee !== '' ? $incomingAssignee : null;
            if ($incomingAssignee !== '') {
                $updateData['assigned_role_id'] = null;
            }

            if ($incomingAssignee !== '' && $incomingAssignee !== $existingAssignee) {
                $updateData['assigned_at'] = date('Y-m-d H:i:s');
                $assignmentChanged = true;
                $isReassignment = ($existingAssignee !== '');
            }

            if ($incomingAssignee === '' && $existingAssignee !== '') {
                $updateData['assigned_at'] = null;
                $wasUnassigned = true;
            }
        }

        if (array_key_exists('assigned_role_id', $updateData)) {
            $incomingRole = trim((string) ($updateData['assigned_role_id'] ?? ''));
            $existingRole = (string) ($report['assigned_role_id'] ?? '');
            $updateData['assigned_role_id'] = $incomingRole !== '' ? $incomingRole : null;

            if ($incomingRole !== '') {
                $updateData['assigned_to_user_id'] = null;
                if ($incomingRole !== $existingRole) {
                    $updateData['assigned_at'] = date('Y-m-d H:i:s');
                    $assignmentChanged = false;
                }
            }

            if ($incomingRole === '' && $existingRole !== '' && empty($updateData['assigned_to_user_id'])) {
                $updateData['assigned_at'] = null;
            }
        }

        
        $this->repository->updateReport($reference, $updateData, $hrUserId);
        
        
        $details = json_encode($updateData);
        $this->repository->logAudit("hr:{$hrUserId}", 'case_updated', $reference, $details, $hrUserId);

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

        foreach ($files['name'] ?? [] as $index => $name) {
            $error = $files['error'][$index] ?? null;
            $tmpName = $files['tmp_name'][$index] ?? null;
            $size = $files['size'][$index] ?? 0;

            if ($error !== UPLOAD_ERR_OK || !$tmpName) {
                continue;
            }

            if ($size > $maxSize) {
                throw new \RuntimeException("File {$name} exceeds maximum size", 400);
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                throw new \RuntimeException("File type {$ext} not allowed", 400);
            }

            
            $mimeMap = [
                'pdf'  => ['application/pdf'],
                'doc'  => ['application/msword'],
                'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                           'application/zip'], 
                'xls'  => ['application/vnd.ms-excel'],
                'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                           'application/zip'],
                'ppt'  => ['application/vnd.ms-powerpoint'],
                'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation',
                           'application/zip'],
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
                'zip'  => ['application/zip', 'application/x-zip-compressed'],
                'rar'  => ['application/vnd.rar', 'application/x-rar-compressed'],
                '7z'   => ['application/x-7z-compressed'],
            ];

            
            if (function_exists('finfo_open')) {
                $finfo        = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                $allowedMimes = $mimeMap[$ext] ?? [];
                if (!empty($allowedMimes) && !in_array($detectedMime, $allowedMimes, true)) {
                    throw new \RuntimeException(
                        "File {$name} content type ({$detectedMime}) does not match its declared extension",
                        400
                    );
                }
            }

            $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
            $uploadPath = $uploadDir . DIRECTORY_SEPARATOR . $storedName;

            if (!move_uploaded_file($tmpName, $uploadPath)) {
                throw new \RuntimeException("Failed to store file {$name}", 500);
            }

            // Scan file for malware
            try {
                if (!$this->malwareScanner->scan($uploadPath)) {
                    // Malware detected, delete the file
                    @unlink($uploadPath);
                    throw new \RuntimeException(
                        "File {$name} failed malware scan and was rejected. Contact support if you believe this is an error.",
                        422
                    );
                }
            } catch (\RuntimeException $e) {
                // If it's our malware detection error, re-throw it
                if ($e->getCode() === 422) {
                    throw $e;
                }
                // For other scanner errors (network, etc), log but allow file to proceed
                // In production, consider being stricter
                error_log("Malware scan warning for {$name}: " . $e->getMessage());
            }

            $mimeType = $mimeMap[$ext][0] ?? 'application/octet-stream';
            
            $attachmentId = $this->repository->saveAttachment(
                $feedbackId,
                $updateId,
                $name,
                $storedName,
                $mimeType,
                $size
            );

            $stored[] = [
                'id' => $attachmentId,
                'name' => $name,
                'stored_name' => $storedName,
                'size' => $size
            ];
        }

        return $stored;
    }

    // ========== Co-Investigator Management ==========

    public function addCoInvestigator(string $feedbackId, string $userId, ?string $addedByUserId = null): array {
        // Get feedback to verify it exists
        $feedback = $this->repository->findById($feedbackId);
        if (!$feedback) {
            throw new \RuntimeException('Feedback not found', 404);
        }

        // Prevent adding the primary assignee as a co-investigator
        if ($feedback['assigned_to_user_id'] === $userId) {
            throw new \RuntimeException('Primary investigator cannot be added as co-investigator', 422);
        }

        // Add the co-investigator
        $added = $this->repository->addCoInvestigator($feedbackId, $userId, $addedByUserId);
        if (!$added) {
            throw new \RuntimeException('User is already a co-investigator', 409);
        }

        // Send notification to co-investigator
        try {
            $coInvestigator = $this->repository->getUserById($userId);
            $addedByUser = $addedByUserId ? $this->repository->getUserById($addedByUserId) : null;
            
            if ($coInvestigator && $coInvestigator['email']) {
                $this->notificationService->notifyCoInvestigatorAdded(
                    $feedbackId,
                    (string) $feedback['reference_no'],
                    (string) $feedback['category'],
                    (string) $coInvestigator['email'],
                    (string) $coInvestigator['name'],
                    $addedByUser ? (string) $addedByUser['name'] : 'HR User'
                );
            }
        } catch (\Exception $e) {
            // Log error but don't fail the operation
            error_log("Failed to send co-investigator notification: " . $e->getMessage());
        }

        return $this->repository->getCoInvestigators($feedbackId);
    }

    public function removeCoInvestigator(string $feedbackId, string $userId, ?string $removedByUserId = null): array {
        $feedback = $this->repository->findById($feedbackId);
        if (!$feedback) {
            throw new \RuntimeException('Feedback not found', 404);
        }

        $this->repository->removeCoInvestigator($feedbackId, $userId);
        
        // Send notification to removed co-investigator
        try {
            $coInvestigator = $this->repository->getUserById($userId);
            $removedByUser = $removedByUserId ? $this->repository->getUserById($removedByUserId) : null;
            
            if ($coInvestigator && $coInvestigator['email']) {
                $this->notificationService->notifyCoInvestigatorRemoved(
                    $feedbackId,
                    (string) $feedback['reference_no'],
                    (string) $feedback['category'],
                    (string) $coInvestigator['email'],
                    (string) $coInvestigator['name'],
                    $removedByUser ? (string) $removedByUser['name'] : 'HR User'
                );
            }
        } catch (\Exception $e) {
            // Log error but don't fail the operation
            error_log("Failed to send co-investigator removal notification: " . $e->getMessage());
        }

        return $this->repository->getCoInvestigators($feedbackId);
    }

    public function getCoInvestigators(string $feedbackId): array {
        $feedback = $this->repository->findById($feedbackId);
        if (!$feedback) {
            throw new \RuntimeException('Feedback not found', 404);
        }

        return $this->repository->getCoInvestigators($feedbackId);
    }

    public function replaceCoInvestigators(string $feedbackId, array $userIds, ?string $updatedByUserId = null): array {
        $feedback = $this->repository->findById($feedbackId);
        if (!$feedback) {
            throw new \RuntimeException('Feedback not found', 404);
        }

        // Get current co-investigators
        $currentCoInvestigators = $this->repository->getCoInvestigators($feedbackId);
        $currentUserIds = array_column($currentCoInvestigators, 'user_id');

        // Remove all existing co-investigators
        $this->repository->removeAllCoInvestigators($feedbackId);

        // Send removal notifications
        try {
            $removedByUser = $updatedByUserId ? $this->repository->getUserById($updatedByUserId) : null;
            foreach ($currentUserIds as $userId) {
                $coInvestigator = $this->repository->getUserById($userId);
                if ($coInvestigator && $coInvestigator['email']) {
                    $this->notificationService->notifyCoInvestigatorRemoved(
                        $feedbackId,
                        (string) $feedback['reference_no'],
                        (string) $feedback['category'],
                        (string) $coInvestigator['email'],
                        (string) $coInvestigator['name'],
                        $removedByUser ? (string) $removedByUser['name'] : 'HR User'
                    );
                }
            }
        } catch (\Exception $e) {
            error_log("Failed to send co-investigator removal notifications: " . $e->getMessage());
        }

        // Add new co-investigators
        try {
            $addedByUser = $updatedByUserId ? $this->repository->getUserById($updatedByUserId) : null;
            foreach ($userIds as $userId) {
                // Prevent adding the primary assignee as a co-investigator
                if ($feedback['assigned_to_user_id'] !== $userId) {
                    $this->repository->addCoInvestigator($feedbackId, $userId, $updatedByUserId);
                    
                    // Send notification to newly added co-investigator
                    $coInvestigator = $this->repository->getUserById($userId);
                    if ($coInvestigator && $coInvestigator['email']) {
                        $this->notificationService->notifyCoInvestigatorAdded(
                            $feedbackId,
                            (string) $feedback['reference_no'],
                            (string) $feedback['category'],
                            (string) $coInvestigator['email'],
                            (string) $coInvestigator['name'],
                            $addedByUser ? (string) $addedByUser['name'] : 'HR User'
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Failed to add co-investigators or send notifications: " . $e->getMessage());
        }

        return $this->repository->getCoInvestigators($feedbackId);
    }
}
