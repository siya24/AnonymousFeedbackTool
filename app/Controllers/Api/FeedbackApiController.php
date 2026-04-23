<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Models\FeedbackModel;

final class FeedbackApiController
{
    private FeedbackModel $model;

    public function __construct()
    {
        $this->model = new FeedbackModel(Container::get('db'));
    }

    public function submit(array $params = []): void
    {
        $input = Request::input();
        $category = trim((string) ($input['category'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));

        if ($category === '' || $description === '' || mb_strlen($description) > 5000) {
            Response::json(['error' => 'Valid category and description (max 5000 chars) are required.'], 422);
        }

        $created = $this->model->createReport($category, $description);
        $this->storeAttachments($created['id'], null);
        $this->model->logAudit('Employee', 'New anonymous feedback submitted', $created['reference_no'], 'Initial submission');
        $this->model->logNotification($created['id'], 'new', 'HR Officer');

        Response::json([
            'message' => 'Feedback submitted successfully.',
            'reference_no' => $created['reference_no'],
            'warning' => 'Keep this reference number safe for follow-up and tracking.',
        ], 201);
    }

    public function submitUpdate(array $params = []): void
    {
        $input = Request::input();
        $referenceNo = strtoupper(trim((string) ($input['reference_no'] ?? '')));
        $updateText = trim((string) ($input['update_text'] ?? ''));

        if ($referenceNo === '' || $updateText === '' || mb_strlen($updateText) > 5000) {
            Response::json(['error' => 'Reference number and update text (max 5000 chars) are required.'], 422);
        }

        try {
            $created = $this->model->createUpdate($referenceNo, $updateText);
            $this->storeAttachments($created['report_id'], $created['update_id']);
            $this->model->logAudit('Employee', 'Follow-up update submitted', $referenceNo, 'Update reference: ' . $created['update_reference_no']);
        } catch (\RuntimeException $e) {
            Response::json(['error' => $e->getMessage()], 404);
        }

        Response::json([
            'message' => 'Follow-up submitted successfully.',
            'update_reference_no' => $created['update_reference_no'],
            'reference_no' => $referenceNo,
        ]);
    }

    public function getByReference(array $params): void
    {
        $referenceNo = strtoupper((string) ($params['reference'] ?? ''));
        $detail = $this->model->getCaseDetail($referenceNo);
        if ($detail === null) {
            Response::json(['error' => 'Reference not found.'], 404);
        }

        Response::json([
            'reference_no' => $detail['report']['reference_no'],
            'category' => $detail['report']['category'],
            'description' => $detail['report']['description'],
            'status' => $detail['report']['status'],
            'created_at' => $detail['report']['created_at'],
            'updates' => $detail['updates'],
        ]);
    }

    public function publicReports(array $params = []): void
    {
        $filters = [
            'reference_no' => Request::query('reference_no'),
            'category' => Request::query('category'),
            'status' => Request::query('status'),
            'date' => Request::query('date'),
        ];

        $reports = $this->model->listPublicReports($filters);
        Response::json(['data' => $reports]);
    }

    private function storeAttachments(int $reportId, ?int $updateId): void
    {
        if (!isset($_FILES['attachments'])) {
            return;
        }

        $allowedExt = ['pdf', 'doc', 'docx', 'txt', 'png', 'jpg', 'jpeg', 'mp3', 'wav', 'm4a'];
        $files = $_FILES['attachments'];

        $count = is_array($files['name']) ? count($files['name']) : 1;
        for ($i = 0; $i < $count; $i++) {
            $name = is_array($files['name']) ? (string) $files['name'][$i] : (string) $files['name'];
            $tmp = is_array($files['tmp_name']) ? (string) $files['tmp_name'][$i] : (string) $files['tmp_name'];
            $error = is_array($files['error']) ? (int) $files['error'][$i] : (int) $files['error'];
            $size = is_array($files['size']) ? (int) $files['size'][$i] : (int) $files['size'];
            $type = is_array($files['type']) ? (string) $files['type'][$i] : (string) $files['type'];

            if ($error !== UPLOAD_ERR_OK || $size > (10 * 1024 * 1024)) {
                continue;
            }

            $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                continue;
            }

            $stored = bin2hex(random_bytes(10)) . '.' . $ext;
            $targetDir = __DIR__ . '/../../../uploads';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0775, true);
            }
            $destination = $targetDir . DIRECTORY_SEPARATOR . $stored;
            if (!move_uploaded_file($tmp, $destination)) {
                continue;
            }

            $this->model->saveAttachment($reportId, $updateId, $name, $stored, $type ?: 'application/octet-stream', $size);
        }
    }
}
