<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Exceptions\ForbiddenException;
use App\Exceptions\ValidationException;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Authorization;
use App\Services\FeedbackService;
use App\Services\HrLdapUserService;
use PDO;

final class HrApiController
{
    private const ASSIGNMENT_ROLE_CASE_MANAGER = 'Case Manager';
    private const MSG_CO_INVESTIGATOR_READ_ONLY = 'Co-investigators can view this case but cannot edit it.';

    private FeedbackService $feedbackService;
    private HrLdapUserService $hrLdapUserService;
    private Authorization $auth;
    private PDO $db;
    private array $appConfig;

    public function __construct()
    {
        $this->feedbackService = Container::get('feedbackService');
        $this->hrLdapUserService = Container::get('hrLdapUserService');
        $this->auth = Container::get('auth');
        $this->db = Container::get('db');
        $this->appConfig = Container::get('config')['app'] ?? [];
    }

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

    
    public function login(): void
    {
        $input = Request::input();
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if (empty($email) || empty($password)) {
            Response::json(['error' => 'Email and password required'], 400);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->checkLoginRateLimit($ip);

        $mode = strtolower((string) ($this->appConfig['hr_auth_mode'] ?? 'local'));
        if (!in_array($mode, ['local', 'ldap', 'hybrid'], true)) {
            $mode = 'local';
        }

        $user = null;

        try {
            if ($mode === 'local' || $mode === 'hybrid') {
                $user = $this->authenticateLocal($email, $password);
            }

            if ($user === null && ($mode === 'ldap' || $mode === 'hybrid')) {
                $user = $this->hrLdapUserService->authenticate($email, $password);
            }
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            $this->recordLoginAttempt($ip, false);
            Response::json(['error' => $e->getMessage()], $code);
        }

        if ($user === null) {
            $this->recordLoginAttempt($ip, false);
            Response::json(['error' => 'Invalid credentials'], 401);
        }

        $this->recordLoginAttempt($ip, true);

        
        $jwt = Container::get('jwt');
        $userId = (string) ($user['id'] ?? '');
        $canAssignCases = $this->resolveCanAssignCases($user);
        $isCaseManager = $this->userHasAssignmentRole($userId, self::ASSIGNMENT_ROLE_CASE_MANAGER);
        $token = $jwt->encode([
            'user_id' => $userId,
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
            'can_assign_cases' => $canAssignCases,
            'is_case_manager' => $isCaseManager ? 1 : 0,
        ]);

        Response::json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $userId,
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'can_assign_cases' => $canAssignCases,
                'is_case_manager' => $isCaseManager,
            ]
        ]);
    }

    private function authenticateLocal(string $identifier, string $password): ?array
    {
        $placeholders = implode(',', array_fill(0, count(Authorization::CONSOLE_ROLES), '?'));
        $stmt = $this->db->prepare(
                        "SELECT id, name, email, password_hash, role, can_assign_cases FROM users
             WHERE (LOWER(email) = LOWER(?) OR LOWER(ad_username) = LOWER(?))
               AND role IN ({$placeholders})
               AND is_active = 1"
        );
        $stmt->execute([$identifier, $identifier, ...Authorization::CONSOLE_ROLES]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $user;
    }

    private function checkLoginRateLimit(string $ip): void
    {
        $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM login_attempts
                 WHERE ip = ? AND success = 0 AND attempted_at >= DATEADD(MINUTE, -15, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([$ip]);
        if ((int) $stmt->fetchColumn() >= 5) {
            Response::json(['error' => 'Too many failed login attempts. Please try again in 15 minutes.'], 429);
        }
    }

    private function recordLoginAttempt(string $ip, bool $success): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO login_attempts (id, ip, success) VALUES (?, ?, ?)'
            );
            $stmt->execute([self::generateUuid(), $ip, $success ? 1 : 0]);
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[%s] Failed to record login attempt: %s',
                (new \DateTimeImmutable('now'))->format(DATE_ATOM),
                $e->getMessage()
            ));
        }
    }


    
    public function logout(): void
    {
        Response::json(['message' => 'Logged out successfully']);
    }

    
    public function listCases(): void
    {
        try {
            
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONSOLE_ROLES);

            $filters = [
                'reference_no' => Request::query('reference_no'),
                'category' => Request::query('category'),
                'status' => Request::query('status'),
                'date' => Request::query('date'),
                'sort_by' => Request::query('sort_by', 'created_at'),
                'sort_order' => Request::query('sort_order', 'DESC'),
            ];

            $page = max(1, (int) Request::query('page', '1'));
            $perPage = max(1, min(100, (int) Request::query('per_page', '10')));

            $result = $this->feedbackService->listCasesForHr($filters, $page, $perPage);
            Response::json([
                'data' => $result['items'],
                'pagination' => [
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'per_page' => $result['per_page'],
                    'total_pages' => $result['total_pages'],
                ],
            ]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    
    public function caseDetail(array $params): void
    {
        try {
            
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONSOLE_ROLES);

            $reference = strtoupper(trim((string) ($params['reference'] ?? '')));
            
            if (empty($reference)) {
                throw new ValidationException('Reference number required', 400);
            }

            $detail = $this->feedbackService->getCaseDetails($reference);
            Response::json(['data' => $detail]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    
    public function updateCase(array $params): void
    {
        try {
            
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CASE_WRITE_ROLES);

            $reference = strtoupper(trim((string) ($params['reference'] ?? '')));
            $payload = Request::input();

            if (empty($reference)) {
                throw new ValidationException('Reference number required', 400);
            }

            $user = $this->auth->getUser();
            $userId = $user['user_id'] ?? 'unknown';
            $userName = (string) ($user['name'] ?? 'HR user');
            $isCaseManager = $this->userHasAssignmentRole((string) $userId, self::ASSIGNMENT_ROLE_CASE_MANAGER);

            if ($this->isCoInvestigatorForCase($reference, (string) $userId)) {
                throw new ForbiddenException(self::MSG_CO_INVESTIGATOR_READ_ONLY);
            }

            $currentAnonymizedSummary = $this->getCurrentAnonymizedSummary($reference);
            if (!$isCaseManager) {
                if (array_key_exists('anonymized_summary', $payload)) {
                    $incomingAnonymizedSummary = trim((string) ($payload['anonymized_summary'] ?? ''));
                    if ($incomingAnonymizedSummary !== trim($currentAnonymizedSummary)) {
                        throw new ForbiddenException('Only Case Manager users can update Anonymized Summary.');
                    }
                }
                $payload['anonymized_summary'] = $currentAnonymizedSummary;
            }

            if ($this->isAssignmentChangeRequested($reference, $payload) && !$this->userCanAssignCases((string) $userId)) {
                throw new ForbiddenException('You do not have authority to assign cases.');
            }

            
            $result = $this->feedbackService->updateCaseForHr($reference, $payload, (string) $userId, $userName);
            Response::json($result);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    
    public function getCurrentUser(): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAuth();

            $user = $this->auth->getUser();
            $userId = (string) ($user['user_id'] ?? '');
            Response::json([
                'user' => [
                    'id' => $userId,
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'can_assign_cases' => $this->userCanAssignCases($userId),
                    'is_case_manager' => $this->userHasAssignmentRole($userId, self::ASSIGNMENT_ROLE_CASE_MANAGER),
                ]
            ]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    public function dbIdentity(): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONSOLE_ROLES);

            $stmt = $this->db->query(
                'SELECT SYSTEM_USER AS system_user, ORIGINAL_LOGIN() AS original_login, SUSER_SNAME() AS suser_sname'
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            Response::json([
                'sql_identity' => $row,
                'iis_identity' => [
                    'remote_user' => (string) ($_SERVER['REMOTE_USER'] ?? ''),
                    'auth_user' => (string) ($_SERVER['AUTH_USER'] ?? ''),
                    'logon_user' => (string) ($_SERVER['LOGON_USER'] ?? ''),
                ],
            ]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    
    private function isAssignmentChangeRequested(string $reference, array $payload): bool
    {
        $hasAssignmentKeys = array_key_exists('assigned_to_user_id', $payload)
            || array_key_exists('assigned_role_id', $payload);
        if (!$hasAssignmentKeys) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT TOP 1 assigned_to_user_id, assigned_role_id
             FROM feedbacks
             WHERE reference_no = ?'
        );
        $stmt->execute([$reference]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $userChanged = false;
        if (array_key_exists('assigned_to_user_id', $payload)) {
            $incoming = trim((string) ($payload['assigned_to_user_id'] ?? ''));
            $existing = trim((string) ($current['assigned_to_user_id'] ?? ''));
            $userChanged = $incoming !== $existing;
        }

        $roleChanged = false;
        if (array_key_exists('assigned_role_id', $payload)) {
            $incoming = trim((string) ($payload['assigned_role_id'] ?? ''));
            $existing = trim((string) ($current['assigned_role_id'] ?? ''));
            $roleChanged = $incoming !== $existing;
        }

        return $userChanged || $roleChanged;
    }

    private function userCanAssignCases(string $userId): bool
    {
        if ($userId === '' || $userId === 'unknown') {
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

    private function resolveCanAssignCases(array $user): int
    {
        if (array_key_exists('can_assign_cases', $user)) {
            return (int) $user['can_assign_cases'];
        }

        return $this->userCanAssignCases((string) ($user['id'] ?? '')) ? 1 : 0;
    }

    private function getCurrentAnonymizedSummary(string $reference): string
    {
        $stmt = $this->db->prepare(
            'SELECT TOP 1 anonymized_summary
             FROM feedbacks
             WHERE reference_no = ?'
        );
        $stmt->execute([$reference]);

        return trim((string) ($stmt->fetchColumn() ?: ''));
    }

    private function isCoInvestigatorForCase(string $reference, string $userId): bool
    {
        if ($reference === '' || $userId === '' || $userId === 'unknown') {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT TOP 1 1
             FROM feedbacks f
             INNER JOIN feedback_co_investigators fci ON fci.feedback_id = f.id
             WHERE f.reference_no = ?
               AND fci.user_id = ?'
        );
        $stmt->execute([$reference, $userId]);

        return (bool) $stmt->fetchColumn();
    }

    private function userHasAssignmentRole(string $userId, string $roleName): bool
    {
        if ($userId === '' || $roleName === '') {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT TOP 1 1
             FROM user_roles ur
             INNER JOIN assignment_roles ar ON ar.id = ur.role_id
             WHERE ur.user_id = ?
               AND LOWER(ar.name) = LOWER(?)'
        );
        $stmt->execute([$userId, trim($roleName)]);

        return (bool) $stmt->fetchColumn();
    }
}

