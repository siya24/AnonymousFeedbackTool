<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Models\FeedbackModel;

final class HrApiController
{
    private FeedbackModel $model;

    public function __construct()
    {
        $this->model = new FeedbackModel(Container::get('db'));
    }

    public function login(array $params = []): void
    {
        $input = Request::input();
        $password = (string) ($input['password'] ?? '');
        $expected = getenv('HR_CONSOLE_PASSWORD') ?: 'ChangeMe123!';

        if (!hash_equals($expected, $password)) {
            Response::json(['error' => 'Invalid credentials.'], 401);
        }

        $_SESSION['hr_authenticated'] = true;
        Response::json(['message' => 'Login successful.']);
    }

    public function logout(array $params = []): void
    {
        $_SESSION['hr_authenticated'] = false;
        Response::json(['message' => 'Logged out.']);
    }

    public function listCases(array $params = []): void
    {
        $this->guard();

        $filters = [
            'reference_no' => Request::query('reference_no'),
            'category' => Request::query('category'),
            'status' => Request::query('status'),
        ];

        $cases = $this->model->listCases($filters);
        Response::json(['data' => $cases]);
    }

    public function caseDetail(array $params): void
    {
        $this->guard();
        $referenceNo = strtoupper((string) ($params['reference'] ?? ''));
        $detail = $this->model->getCaseDetail($referenceNo);
        if ($detail === null) {
            Response::json(['error' => 'Case not found.'], 404);
        }

        Response::json(['data' => $detail]);
    }

    public function updateCase(array $params): void
    {
        $this->guard();

        $referenceNo = strtoupper((string) ($params['reference'] ?? ''));
        $payload = Request::input();

        try {
            $this->model->updateCase($referenceNo, $payload);
            $this->model->logAudit('HR Officer', 'Case updated', $referenceNo, 'Status: ' . ((string) ($payload['status'] ?? 'unchanged')));
        } catch (\RuntimeException $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }

        Response::json(['message' => 'Case updated successfully.']);
    }

    private function guard(): void
    {
        if (empty($_SESSION['hr_authenticated'])) {
            Response::json(['error' => 'Unauthorized'], 401);
        }
    }
}
