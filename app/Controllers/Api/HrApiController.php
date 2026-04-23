<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Authorization;
use App\Services\FeedbackService;
use PDO;

final class HrApiController
{
    private FeedbackService $feedbackService;
    private Authorization $auth;
    private PDO $db;

    public function __construct()
    {
        $this->feedbackService = Container::get('feedbackService');
        $this->auth = Container::get('auth');
        $this->db = Container::get('db');
    }

    /**
     * JWT-based authentication for HR/Ethics users
     * POST /api/hr/login
     * Body: { "email": "...", "password": "..." }
     */
    public function login(array $params = []): void
    {
        $input = Request::input();
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if (empty($email) || empty($password)) {
            Response::json(['error' => 'Email and password required'], 400);
        }

        // Find user in database
        $stmt = $this->db->prepare(
            'SELECT id, name, email, password_hash, role FROM users WHERE email = ? AND is_active = 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            Response::json(['error' => 'Invalid credentials'], 401);
        }

        // Generate JWT token
        $jwt = Container::get('jwt');
        $token = $jwt->encode([
            'user_id' => (int)$user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role']
        ]);

        Response::json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ]);
    }

    /**
     * Logout (client-side removes JWT token)
     * POST /api/hr/logout
     */
    public function logout(array $params = []): void
    {
        Response::json(['message' => 'Logged out successfully']);
    }

    /**
     * List cases for authenticated HR/Ethics users
     * GET /api/hr/cases
     * Query: ?reference_no=...&category=...&status=...
     */
    public function listCases(array $params = []): void
    {
        try {
            // Authenticate and authorize
            $this->auth->authenticate();
            $this->auth->requireAnyRole([Authorization::ROLE_HR, Authorization::ROLE_ETHICS]);

            $filters = [
                'reference_no' => Request::query('reference_no'),
                'category' => Request::query('category'),
                'status' => Request::query('status'),
            ];

            $cases = $this->feedbackService->listCasesForHr($filters);
            Response::json(['data' => $cases]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    /**
     * Get detailed case information
     * GET /api/hr/cases/{reference}
     */
    public function caseDetail(array $params): void
    {
        try {
            // Authenticate and authorize
            $this->auth->authenticate();
            $this->auth->requireAnyRole([Authorization::ROLE_HR, Authorization::ROLE_ETHICS]);

            $reference = strtoupper(trim((string) ($params['reference'] ?? '')));
            
            if (empty($reference)) {
                throw new \RuntimeException('Reference number required', 400);
            }

            $detail = $this->feedbackService->getCaseDetails($reference);
            Response::json(['data' => $detail]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    /**
     * Update case status, priority, notes, etc.
     * POST /api/hr/cases/{reference}
     * Body: { "status": "...", "priority": "...", "internal_notes": "...", ... }
     */
    public function updateCase(array $params): void
    {
        try {
            // Authenticate and authorize (HR role required for updates)
            $this->auth->authenticate();
            $this->auth->requireRole(Authorization::ROLE_HR);

            $reference = strtoupper(trim((string) ($params['reference'] ?? '')));
            $payload = Request::input();

            if (empty($reference)) {
                throw new \RuntimeException('Reference number required', 400);
            }

            $user = $this->auth->getUser();
            $userId = $user['user_id'] ?? 'unknown';

            // Update through service layer
            $result = $this->feedbackService->updateCaseForHr($reference, $payload, (string)$userId);
            Response::json($result);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    /**
     * Get current authenticated user info
     * GET /api/hr/me
     */
    public function getCurrentUser(array $params = []): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAuth();

            $user = $this->auth->getUser();
            Response::json([
                'user' => [
                    'id' => $user['user_id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }
}

