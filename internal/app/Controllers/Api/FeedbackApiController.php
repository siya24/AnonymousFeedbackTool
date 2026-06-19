<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Authorization;
use App\Services\CoInvestigatorService;
use App\Services\FeedbackService;
use PDO;

final class FeedbackApiController
{
    private const MSG_ASSIGN_DENIED = 'You do not have authority to assign cases.';
    private const MSG_CO_INVESTIGATOR_READ_ONLY = 'Co-investigators can view this case but cannot edit it.';

    private FeedbackService $feedbackService;
    private CoInvestigatorService $coInvestigatorService;
    private Authorization $auth;
    private PDO $db;

    public function __construct()
    {
        $this->feedbackService = Container::get('feedbackService');
        $this->coInvestigatorService = Container::get('coInvestigatorService');
        $this->auth = Container::get('auth');
        $this->db = Container::get('db');
    }

    
    public function submit(): void
    {
        try {
            $input = Request::input();
            $category = trim((string) ($input['category'] ?? ''));
            $description = trim((string) ($input['description'] ?? ''));
            $categoryOther = trim((string) ($input['category_other'] ?? ''));

            if ($category === '' || $description === '' || mb_strlen($description) > 5000) {
                Response::json(['error' => 'Valid category and description (max 5000 chars) are required.'], 422);
            }

            if ($category === 'Other' && $categoryOther === '') {
                Response::json(['error' => 'Please specify the category when selecting Other.'], 422);
            }

            $result = $this->feedbackService->submitFeedback($category, $description, $categoryOther !== '' ? $categoryOther : null);
            
            
            if (isset($_FILES['attachments'])) {
                $this->feedbackService->storeAttachments(
                    (string) ($result['feedback_id'] ?? $result['report_id'] ?? ''),
                    null,
                    $_FILES['attachments']
                );
            }

            Response::json([
                'message' => $result['message'],
                'reference_no' => $result['reference'],
                'warning' => 'Keep this reference number safe for follow-up and tracking.',
            ], 201);
        } catch (\Throwable $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    
    public function submitUpdate(): void
    {
        try {
            $input = Request::input();
            $referenceNo = strtoupper(trim((string) ($input['reference_no'] ?? '')));
            $updateText = trim((string) ($input['update_text'] ?? ''));

            if ($referenceNo === '' || $updateText === '' || mb_strlen($updateText) > 5000) {
                Response::json(['error' => 'Reference number and update text (max 5000 chars) are required.'], 422);
            }

            $result = $this->feedbackService->submitFollowUp($referenceNo, $updateText);
            
            
            if (isset($_FILES['attachments'])) {
                $reportDetail = $this->feedbackService->getCaseDetails($referenceNo);
                $reportFeedbackId = (string) ($reportDetail['report']['id'] ?? '');
                $this->feedbackService->storeAttachments(
                    $reportFeedbackId,
                    $result['update_id'],
                    $_FILES['attachments']
                );
            }

            Response::json([
                'message' => $result['message'],
                'update_reference_no' => $result['update_reference'],
                'reference_no' => $referenceNo,
            ]);
        } catch (\Throwable $e) {
            $code = (int) ($e->getCode() ?: 400);
            if ($code < 400 || $code > 599) {
                $code = 400;
            }
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    
    public function getByReference(array $params): void
    {
        try {
            $reference = strtoupper((string) ($params['reference'] ?? ''));
            
            if (empty($reference)) {
                Response::json(['error' => 'Reference number required'], 400);
            }

            $detail = $this->feedbackService->getCaseDetails($reference);

            Response::json([
                'reference_no' => $detail['report']['reference_no'],
                'category' => $detail['report']['category'],
                'description' => $detail['report']['description'],
                'status' => $detail['report']['status'],
                'created_at' => $detail['report']['created_at'],
                'anonymized_summary' => $detail['report']['anonymized_summary'] ?? null,
                'updates' => $detail['updates'],
                'attachments' => $detail['attachments'],
            ]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    
    public function downloadAttachment(array $params): void
    {
        $id = trim((string) ($params['id'] ?? ''));
        if ($id === '') {
            Response::json(['error' => 'Invalid attachment ID'], 400);
        }

        $repository = \App\Core\Container::get('attachmentRepository');
        $attachment = $repository->getAttachmentById($id);

        if (!$attachment) {
            Response::json(['error' => 'Attachment not found'], 404);
        }

        // Allow access if the request carries a valid HR JWT token
        $auth = \App\Core\Container::get('auth');
        $isAuthenticated = $auth->authenticate() && $auth->isAuthenticated();

        if (!$isAuthenticated) {
            // Allow anonymous access only when the caller provides the correct reference_no
            $referenceNo = strtoupper(trim((string) (\App\Core\Request::query('reference_no') ?? '')));
            if ($referenceNo === '') {
                Response::json(['error' => 'Authentication required to download attachments'], 401);
            }

            // Verify the attachment belongs to the supplied reference number
            $db = \App\Core\Container::get('db');
            $stmt = $db->prepare(
                 'SELECT TOP 1 f.id FROM feedbacks f
                 LEFT JOIN attachments a ON a.feedback_id = f.id
                 LEFT JOIN report_updates ru ON a.report_update_id = ru.id
                 LEFT JOIN feedbacks f2 ON ru.feedback_id = f2.id
                 WHERE f.reference_no = ? AND (a.id = ? OR (a.report_update_id IS NOT NULL AND f2.reference_no = ?))
                  '
            );
            $stmt->execute([$referenceNo, $id, $referenceNo]);
            if (!$stmt->fetchColumn()) {
                Response::json(['error' => 'Access denied'], 403);
            }
        }

        $config = \App\Core\Container::get('config');
        $storageDir = trim((string) ($config['app']['attachments_storage_path'] ?? ''));
        if ($storageDir === '') {
            $storageDir = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'anonymous_feedback_private_uploads';
        }
        $storageDir = rtrim($storageDir, "\\/");

        $uploadPath = $storageDir . DIRECTORY_SEPARATOR . basename((string) $attachment['stored_name']);
        if (!file_exists($uploadPath)) {
            Response::json(['error' => 'File not found on server'], 404);
        }

        
        $safeName = preg_replace('/[^\w.\- ]/', '_', $attachment['original_name']);

        header('Content-Type: ' . ($attachment['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . filesize($uploadPath));
        header('Cache-Control: no-store');
        readfile($uploadPath);
        exit;
    }

    
    public function publicReports(): void
    {
        try {
            $filters = [
                'reference_no' => Request::query('reference_no'),
                'category' => Request::query('category'),
                'status' => Request::query('status'),
                'date' => Request::query('date'),
            ];

            $reports = $this->feedbackService->getPublicReports($filters);
            Response::json(['data' => $reports]);
        } catch (\RuntimeException $e) {
            Response::json(['error' => $e->getMessage()], 400);
        }
    }

    // ========== Co-Investigator Management ==========

    public function addCoInvestigator(array $params): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAuth();
            $this->auth->requireAnyRole(Authorization::CASE_WRITE_ROLES);
            $currentUser = $this->auth->getUser();
            if (!$this->userCanAssignCases((string) ($currentUser['user_id'] ?? ''))) {
                Response::json(['error' => self::MSG_ASSIGN_DENIED], 403);
                return;
            }

            $feedbackId = $params['id'] ?? null;
            $input = Request::input();
            $userId = $input['user_id'] ?? null;

            if (!$feedbackId || !$userId) {
                Response::json(['error' => 'Feedback ID and user ID are required.'], 422);
                return;
            }
            if ($this->isCoInvestigatorForFeedback($feedbackId, (string) ($currentUser['user_id'] ?? ''))) {
                Response::json(['error' => self::MSG_CO_INVESTIGATOR_READ_ONLY], 403);
                return;
            }

            $coInvestigators = $this->coInvestigatorService->addCoInvestigator(
                $feedbackId,
                $userId,
                (string) ($currentUser['user_id'] ?? '')
            );
            Response::json(['data' => $coInvestigators, 'message' => 'Co-investigator added successfully.'], 201);
        } catch (\RuntimeException $e) {
            Response::json(['error' => $e->getMessage()], (int) $e->getCode() ?: 400);
        }
    }

    public function removeCoInvestigator(array $params): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAuth();
            $this->auth->requireAnyRole(Authorization::CASE_WRITE_ROLES);
            $currentUser = $this->auth->getUser();
            if (!$this->userCanAssignCases((string) ($currentUser['user_id'] ?? ''))) {
                Response::json(['error' => self::MSG_ASSIGN_DENIED], 403);
                return;
            }

            $feedbackId = $params['id'] ?? null;
            $userId = $params['user_id'] ?? null;

            if (!$feedbackId || !$userId) {
                Response::json(['error' => 'Feedback ID and user ID are required.'], 422);
                return;
            }
            if ($this->isCoInvestigatorForFeedback($feedbackId, (string) ($currentUser['user_id'] ?? ''))) {
                Response::json(['error' => self::MSG_CO_INVESTIGATOR_READ_ONLY], 403);
                return;
            }

            $coInvestigators = $this->coInvestigatorService->removeCoInvestigator(
                $feedbackId,
                $userId,
                (string) ($currentUser['user_id'] ?? '')
            );
            Response::json(['data' => $coInvestigators, 'message' => 'Co-investigator removed successfully.']);
        } catch (\RuntimeException $e) {
            Response::json(['error' => $e->getMessage()], (int) $e->getCode() ?: 400);
        }
    }

    public function getCoInvestigators(array $params): void
    {
        try {
            $feedbackId = $params['id'] ?? null;

            if (!$feedbackId) {
                Response::json(['error' => 'Feedback ID is required.'], 422);
                return;
            }

            $coInvestigators = $this->coInvestigatorService->getCoInvestigators($feedbackId);
            Response::json(['data' => $coInvestigators]);
        } catch (\RuntimeException $e) {
            Response::json(['error' => $e->getMessage()], (int) $e->getCode() ?: 400);
        }
    }

    public function replaceCoInvestigators(array $params): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAuth();
            $this->auth->requireAnyRole(Authorization::CASE_WRITE_ROLES);
            $currentUser = $this->auth->getUser();
            if (!$this->userCanAssignCases((string) ($currentUser['user_id'] ?? ''))) {
                Response::json(['error' => self::MSG_ASSIGN_DENIED], 403);
                return;
            }

            $feedbackId = $params['id'] ?? null;
            $input = Request::input();
            $userIds = $input['user_ids'] ?? [];

            if (!$feedbackId || !is_array($userIds)) {
                Response::json(['error' => 'Feedback ID and user IDs array are required.'], 422);
                return;
            }
            if ($this->isCoInvestigatorForFeedback($feedbackId, (string) ($currentUser['user_id'] ?? ''))) {
                Response::json(['error' => self::MSG_CO_INVESTIGATOR_READ_ONLY], 403);
                return;
            }

            $coInvestigators = $this->coInvestigatorService->replaceCoInvestigators(
                $feedbackId,
                $userIds,
                (string) ($currentUser['user_id'] ?? '')
            );
            Response::json(['data' => $coInvestigators, 'message' => 'Co-investigators updated successfully.']);
        } catch (\RuntimeException $e) {
            Response::json(['error' => $e->getMessage()], (int) $e->getCode() ?: 400);
        }
    }

    private function userCanAssignCases(string $userId): bool
    {
        if ($userId === '') {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT TOP 1 can_assign_cases
             FROM users
             WHERE id = ? AND is_active = 1'
        );
        $stmt->execute([$userId]);

        return ((int) ($stmt->fetchColumn() ?: 0)) === 1;
    }

    private function isCoInvestigatorForFeedback(string $feedbackIdentifier, string $userId): bool
    {
        $identifier = trim($feedbackIdentifier);
        if ($identifier === '' || $userId === '') {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT TOP 1 1
             FROM feedback_co_investigators fci
             INNER JOIN feedbacks f ON f.id = fci.feedback_id
             WHERE fci.user_id = ?
               AND (f.id = ? OR f.reference_no = ?)'
        );
        $stmt->execute([$userId, $identifier, strtoupper($identifier)]);

        return (bool) $stmt->fetchColumn();
    }
}

