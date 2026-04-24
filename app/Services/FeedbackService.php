<?php declare(strict_types=1);

namespace App\Services;

use App\Repositories\FeedbackRepository;
use DateTime;

class FeedbackService {
    public function __construct(
        private FeedbackRepository $repository,
        private NotificationService $notificationService
    ) {}

    /**
     * Generate a unique reference number
     */
    public function generateReference(string $prefix = 'AF'): string {
        $date = (new DateTime())->format('Ymd');
        $random = strtoupper(bin2hex(random_bytes(3)));
        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Submit new feedback
     */
    public function submitFeedback(string $category, string $description): array {
        $reference = $this->generateReference('AF');
        
        $reportId = $this->repository->createReport($reference, $category, $description);
        
        // Log audit trail
        $this->repository->logAudit('anonymous', 'feedback_submitted', $reference, 
            "New feedback submitted in category: {$category}");

        // Send immediate HR email alert and log notification.
        $this->notificationService->notifyNewFeedback($reportId, $reference, $category);
        
        return [
            'success' => true,
            'reference' => $reference,
            'report_id' => $reportId,
            'message' => 'Feedback submitted successfully'
        ];
    }

    /**
     * Submit follow-up to existing feedback
     */
    public function submitFollowUp(string $reference, string $updateText): array {
        $report = $this->repository->findByReference($reference);
        
        if (!$report) {
            throw new \RuntimeException('Feedback case not found', 404);
        }

        $updateReference = $this->generateReference('UPD');
        $updateId = $this->repository->createUpdate((int)$report['id'], $updateReference, $updateText);
        
        // Log audit trail
        $this->repository->logAudit('anonymous', 'followup_submitted', $reference,
            "Follow-up submitted: {$updateReference}");
        
        return [
            'success' => true,
            'update_reference' => $updateReference,
            'update_id' => $updateId,
            'message' => 'Follow-up submitted successfully'
        ];
    }

    /**
     * Get feedback case details
     */
    public function getCaseDetails(string $reference): array {
        $detailed = $this->repository->getDetailedReport($reference);
        
        if (!$detailed) {
            throw new \RuntimeException('Feedback case not found', 404);
        }

        return $detailed;
    }

    /**
     * Get public anonymized reports with filters
     */
    public function getPublicReports(array $filters = []): array {
        return $this->repository->listPublicReports($filters);
    }

    /**
     * Get HR case list with filters
     */
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

    /**
     * Get dashboard trend and status aggregates.
     */
    public function getDashboardTrends(): array {
        return [
            'quarterly_by_category' => $this->repository->getQuarterlyCategoryTrends(),
            'status_totals' => $this->repository->getStatusTotals(),
        ];
    }

    /**
     * Process scheduled notification reminders/escalations.
     */
    public function processScheduledNotifications(): array {
        return $this->notificationService->processScheduledNotifications();
    }

    /**
     * Update case for HR
     */
    public function updateCaseForHr(string $reference, array $updateData, string $hrUserId): array {
        $report = $this->repository->findByReference($reference);
        
        if (!$report) {
            throw new \RuntimeException('Feedback case not found', 404);
        }

        // Validate status transitions
        if ($updateData['status'] === 'Investigation completed' && empty($updateData['outcome_comments'])) {
            throw new \RuntimeException('Outcome comments required when marking as completed', 400);
        }

        // Handle acknowledgement
        if ($updateData['acknowledge'] ?? false) {
            $updateData['acknowledged_at'] = date('Y-m-d H:i:s');
            unset($updateData['acknowledge']);
        } else {
            unset($updateData['acknowledge']);
        }

        // Update the report
        $this->repository->updateReport($reference, $updateData);
        
        // Log audit trail
        $details = json_encode($updateData);
        $this->repository->logAudit("hr:{$hrUserId}", 'case_updated', $reference, $details);
        
        return [
            'success' => true,
            'reference' => $reference,
            'message' => 'Case updated successfully'
        ];
    }

    /**
     * Store multiple attachments
     */
    public function storeAttachments(int $reportId, ?int $updateId, array $files): array {
        $stored = [];
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'mp3', 'wav'];
        $maxSize = 10 * 1024 * 1024; // 10MB

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

            $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
            $uploadPath = __DIR__ . '/../../uploads/' . $storedName;

            if (!move_uploaded_file($tmpName, $uploadPath)) {
                throw new \RuntimeException("Failed to store file {$name}", 500);
            }

            $mimeMap = [
                'pdf'  => 'application/pdf',
                'doc'  => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'gif'  => 'image/gif',
                'mp3'  => 'audio/mpeg',
                'wav'  => 'audio/wav',
            ];
            $mimeType = $mimeMap[$ext] ?? 'application/octet-stream';
            
            $attachmentId = $this->repository->saveAttachment(
                $reportId,
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
