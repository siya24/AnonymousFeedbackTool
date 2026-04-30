<?php declare(strict_types=1);

namespace App\Services;

use App\Repositories\FeedbackRepository;
use DateTime;

class FeedbackService {
    public function __construct(
        private FeedbackRepository $repository,
        private NotificationService $notificationService
    ) {}

    
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

    
    public function processScheduledNotifications(): array {
        
        $pruned = $this->repository->pruneOldAuditLogs(1825);

        $result = $this->notificationService->processScheduledNotifications();
        $result['audit_logs_pruned'] = $pruned;
        return $result;
    }

    
    public function updateCaseForHr(string $reference, array $updateData, string $hrUserId): array {
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

        
        $this->repository->updateReport($reference, $updateData, $hrUserId);
        
        
        $details = json_encode($updateData);
        $this->repository->logAudit("hr:{$hrUserId}", 'case_updated', $reference, $details, $hrUserId);
        
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
        $uploadDir = realpath(__DIR__ . '/../../uploads');
        if ($uploadDir === false) {
            $uploadDir = __DIR__ . '/../../uploads';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true)) {
                throw new \RuntimeException('Failed to initialize internal upload directory', 500);
            }
        }

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
}
