<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Authorization;
use App\Services\FeedbackService;
use App\Services\LdapAuthService;
use PDO;

final class HrApiController
{
    private FeedbackService $feedbackService;
    private LdapAuthService $ldapAuthService;
    private Authorization $auth;
    private PDO $db;
    private array $appConfig;

    public function __construct()
    {
        $this->feedbackService = Container::get('feedbackService');
        $this->ldapAuthService = Container::get('ldapAuthService');
        $this->auth = Container::get('auth');
        $this->db = Container::get('db');
        $this->appConfig = Container::get('config')['app'] ?? [];
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
                $user = $this->authenticateLdap($email, $password);
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

    private function authenticateLocal(string $email, string $password): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, password_hash, role FROM users WHERE email = ? AND is_active = 1'
        );
        $stmt->execute([$email]);
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
             WHERE ip = ? AND success = 0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
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
                'INSERT INTO login_attempts (ip, success) VALUES (?, ?)'
            );
            $stmt->execute([$ip, $success ? 1 : 0]);
        } catch (\Throwable $e) {
            // Non-blocking: do not fail login if logging fails.
        }
    }

    private function authenticateLdap(string $identifier, string $password): ?array
    {
        $profile = $this->ldapAuthService->authenticate($identifier, $password);
        if ($profile === null) {
            return null;
        }

        // ── Group-based access control ────────────────────────────────────────
        // When LDAP groups are configured, ONLY members of those groups may access
        // the HR Console. The role is derived from which group they belong to.
        $hrGroupsRaw = trim((string) ($this->appConfig['ldap_hr_groups'] ?? ''));
        $isGroupsRaw = trim((string) ($this->appConfig['ldap_is_groups'] ?? ''));
        $hrOusRaw    = trim((string) ($this->appConfig['ldap_hr_ous'] ?? ''));
        $isOusRaw    = trim((string) ($this->appConfig['ldap_is_ous'] ?? ''));
        $hrDeptsRaw  = trim((string) ($this->appConfig['ldap_hr_departments'] ?? ''));
        $isDeptsRaw  = trim((string) ($this->appConfig['ldap_is_departments'] ?? ''));
        $groupsConfigured = $hrGroupsRaw !== '' || $isGroupsRaw !== ''
            || $hrOusRaw !== '' || $isOusRaw !== ''
            || $hrDeptsRaw !== '' || $isDeptsRaw !== '';

        if ($groupsConfigured) {
            // Developer override always bypasses group/OU/department restrictions.
            if ($this->isDeveloperOverrideUser($identifier, $profile)) {
                return $this->upsertLdapUser($identifier, $profile, Authorization::ROLE_HR);
            }

            $role = $this->resolveRoleFromLdapProfile($profile);
            if ($role === null) {
                throw new \RuntimeException(
                    'Access denied: your account is not a member of an authorised group (HR or Information Systems).',
                    403
                );
            }
            return $this->upsertLdapUser($identifier, $profile, $role);
        }

        // ── Fallback: groups not configured — use DB lookup + developer override
        $emailCandidates = [];
        $profileEmail = strtolower(trim((string) ($profile['email'] ?? '')));
        $inputEmail = strtolower(trim($identifier));

        if ($profileEmail !== '') {
            $emailCandidates[] = $profileEmail;
        }

        $profileUpn = strtolower(trim((string) ($profile['email_upn'] ?? '')));
        if ($profileUpn !== '') {
            $emailCandidates[] = $profileUpn;
        }

        if (filter_var($inputEmail, FILTER_VALIDATE_EMAIL)) {
            $emailCandidates[] = $inputEmail;
        }

        $emailCandidates = array_values(array_unique($emailCandidates));

        foreach ($emailCandidates as $candidate) {
            $stmt = $this->db->prepare(
                'SELECT id, name, email, password_hash, role FROM users WHERE email = ? AND is_active = 1'
            );
            $stmt->execute([$candidate]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                return $user;
            }
        }

        if ($this->isDeveloperOverrideUser($identifier, $profile)) {
            return $this->upsertLdapUser($identifier, $profile, Authorization::ROLE_HR);
        }

        throw new \RuntimeException('LDAP authenticated, but user is not provisioned for HR Console', 403);
    }

    /**
     * Resolve an HR Console role from the LDAP profile using three layers:
     *   1. Group membership (memberOf)
     *   2. OU path in the user's distinguishedName
     *   3. AD department attribute
     *
     * Returns ROLE_HR on any match, null if none matched.
     *
     * Config values use pipe (|) as delimiter so full DNs with commas are safe.
     */
    private function resolveRoleFromLdapProfile(array $profile): ?string
    {
        // Helper: split pipe-delimited config value into lowercase trimmed tokens.
        $split = static function (string $raw): array {
            return array_values(array_filter(array_map(
                static fn (string $v): string => strtolower(trim($v)),
                explode('|', $raw)
            )));
        };

        $hrGroups      = $split((string) ($this->appConfig['ldap_hr_groups'] ?? ''));
        $isGroups      = $split((string) ($this->appConfig['ldap_is_groups'] ?? ''));
        $hrOus         = $split((string) ($this->appConfig['ldap_hr_ous'] ?? ''));
        $isOus         = $split((string) ($this->appConfig['ldap_is_ous'] ?? ''));
        $hrDepts       = $split((string) ($this->appConfig['ldap_hr_departments'] ?? ''));
        $isDepts       = $split((string) ($this->appConfig['ldap_is_departments'] ?? ''));

        $nothingConfigured = empty($hrGroups) && empty($isGroups)
            && empty($hrOus) && empty($isOus)
            && empty($hrDepts) && empty($isDepts);

        if ($nothingConfigured) {
            return null;
        }

        // ── Layer 1: group membership ────────────────────────────────────────
        foreach (($profile['groups'] ?? []) as $groupDn) {
            $normDn = strtolower(trim($groupDn));
            // Match full DN
            if (in_array($normDn, $hrGroups, true) || in_array($normDn, $isGroups, true)) {
                return Authorization::ROLE_HR;
            }
            // Match by CN alone (e.g. config value "HR" matches "CN=HR,OU=...")
            if (preg_match('/^cn=([^,]+)/i', $groupDn, $m)) {
                $cn = strtolower(trim($m[1]));
                if (in_array($cn, $hrGroups, true) || in_array($cn, $isGroups, true)) {
                    return Authorization::ROLE_HR;
                }
            }
        }

        // ── Layer 2: OU path in distinguishedName ────────────────────────────
        // e.g. "OU=Information Services" is a substring of the user's full DN
        $userDn = strtolower(trim((string) ($profile['distinguished_name'] ?? '')));
        if ($userDn !== '') {
            foreach ($hrOus as $ou) {
                if ($ou !== '' && str_contains($userDn, strtolower($ou))) {
                    return Authorization::ROLE_HR;
                }
            }
            foreach ($isOus as $ou) {
                if ($ou !== '' && str_contains($userDn, strtolower($ou))) {
                    return Authorization::ROLE_HR;
                }
            }
        }

        // ── Layer 3: department attribute ────────────────────────────────────
        $userDept = strtolower(trim((string) ($profile['department'] ?? '')));
        if ($userDept !== '') {
            foreach (array_merge($hrDepts, $isDepts) as $dept) {
                if ($dept !== '' && $userDept === strtolower($dept)) {
                    return Authorization::ROLE_HR;
                }
            }
        }

        return null;
    }

    private function isDeveloperOverrideUser(string $identifier, array $profile): bool
    {
        $raw = (string) ($this->appConfig['developer_override_users'] ?? '');
        if ($raw === '') {
            return false;
        }

        $allowed = array_values(array_filter(array_map(
            static fn ($item): string => strtolower(trim((string) $item)),
            explode(',', $raw)
        )));

        if ($allowed === []) {
            return false;
        }

        $username = strtolower(trim((string) ($profile['username'] ?? '')));
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        $upn = strtolower(trim((string) ($profile['email_upn'] ?? '')));
        $input = strtolower(trim($identifier));

        $candidates = array_values(array_unique(array_filter([$username, $email, $upn, $input])));

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    private function upsertLdapUser(string $identifier, array $profile, string $role): array
    {
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        if ($email === '') {
            $email = strtolower(trim((string) ($profile['email_upn'] ?? '')));
        }
        if ($email === '' && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $email = strtolower(trim($identifier));
        }
        if ($email === '') {
            $username = strtolower(trim((string) ($profile['username'] ?? $identifier)));
            if ($username !== '') {
                $domain = $this->resolveLdapEmailDomain();
                if ($domain !== '') {
                    $email = $username . '@' . $domain;
                }
            }
        }
        if ($email === '') {
            throw new \RuntimeException('LDAP user requires a resolvable email address', 403);
        }

        $name = trim((string) ($profile['name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($profile['username'] ?? $identifier));
        }

        // LDAP users have no local password; placeholder satisfies schema constraints.
        $placeholderHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

        $upsert = $this->db->prepare(
            'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role), is_active = VALUES(is_active)'
        );
        $upsert->execute([$name, $email, $placeholderHash, $role]);

        $find = $this->db->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? AND is_active = 1');
        $find->execute([$email]);
        $user = $find->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new \RuntimeException('Unable to provision LDAP user', 500);
        }

        return $user;
    }

    private function resolveLdapEmailDomain(): string
    {
        $host = strtolower(trim((string) ($this->appConfig['ldap_host'] ?? '')));
        if ($host !== '' && str_contains($host, '.')) {
            return $host;
        }

        $baseDn = strtolower(trim((string) ($this->appConfig['ldap_base_dn'] ?? '')));
        if ($baseDn !== '') {
            preg_match_all('/dc=([^,]+)/i', $baseDn, $matches);
            if (!empty($matches[1])) {
                $parts = array_map(static fn($part): string => trim((string) $part), $matches[1]);
                $parts = array_values(array_filter($parts));
                if ($parts !== []) {
                    return implode('.', $parts);
                }
            }
        }

        return '';
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
    * Query: ?reference_no=...&category=...&status=...&date=...&sort_by=...&sort_order=...
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

    /**
     * Dashboard trends for HR/Ethics users.
     * GET /api/hr/dashboard/trends
     */
    public function dashboardTrends(array $params = []): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole([Authorization::ROLE_HR, Authorization::ROLE_ETHICS]);

            $data = $this->feedbackService->getDashboardTrends();
            Response::json(['data' => $data]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }
}

