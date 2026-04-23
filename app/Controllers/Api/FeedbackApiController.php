<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Services\FeedbackService;

final class FeedbackApiController
{
    private FeedbackService $feedbackService;

    public function __construct()
    {
        $this->feedbackService = Container::get('feedbackService');
    }

    /**
     * Submit new anonymous feedback
     * POST /api/feedback
     */
    public function submit(array $params = []): void
    {
        try {
            $input = Request::input();
            $category = trim((string) ($input['category'] ?? ''));
            $description = trim((string) ($input['description'] ?? ''));

            if ($category === '' || $description === '' || mb_strlen($description) > 5000) {
                Response::json(['error' => 'Valid category and description (max 5000 chars) are required.'], 422);
            }

            $result = $this->feedbackService->submitFeedback($category, $description);
            
            // Handle attachments
            if (isset($_FILES['attachments'])) {
                $this->feedbackService->storeAttachments(
                    $result['report_id'],
                    null,
                    $_FILES['attachments']
                );
            }

            Response::json([
                'message' => $result['message'],
                'reference_no' => $result['reference'],
                'warning' => 'Keep this reference number safe for follow-up and tracking.',
            ], 201);
        } catch (\RuntimeException $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Submit follow-up to existing feedback
     * POST /api/feedback/update
     */
    public function submitUpdate(array $params = []): void
    {
        try {
            $input = Request::input();
            $referenceNo = strtoupper(trim((string) ($input['reference_no'] ?? '')));
            $updateText = trim((string) ($input['update_text'] ?? ''));

            if ($referenceNo === '' || $updateText === '' || mb_strlen($updateText) > 5000) {
                Response::json(['error' => 'Reference number and update text (max 5000 chars) are required.'], 422);
            }

            $result = $this->feedbackService->submitFollowUp($referenceNo, $updateText);
            
            // Handle attachments
            if (isset($_FILES['attachments'])) {
                $this->feedbackService->storeAttachments(
                    $this->feedbackService->getPublicReports(['reference_no' => $referenceNo])[0]['id'] ?? 0,
                    $result['update_id'],
                    $_FILES['attachments']
                );
            }

            Response::json([
                'message' => $result['message'],
                'update_reference_no' => $result['update_reference'],
                'reference_no' => $referenceNo,
            ]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    /**
     * Get feedback case details by reference
     * GET /api/feedback/{reference}
     */
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
                'updates' => $detail['updates'],
                'attachments' => $detail['attachments'],
            ]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    /**
     * Get public anonymized reports
     * GET /api/reports
     */
    public function publicReports(array $params = []): void
    {
        try {
            $filters = [
                'reference_no' => Request::query('reference_no'),
                'category' => Request::query('category'),
                'status' => Request::query('status'),
            ];

            $reports = $this->feedbackService->getPublicReports($filters);
            Response::json(['data' => $reports]);
        } catch (\RuntimeException $e) {
            Response::json(['error' => $e->getMessage()], 400);
        }
    }
}

