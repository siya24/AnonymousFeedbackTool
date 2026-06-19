<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Authorization;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\FeedbackService;
use PDO;

final class HrAdminApiController
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

    public function dashboardTrends(): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONSOLE_ROLES);

            $data = $this->feedbackService->getDashboardTrends();
            Response::json(['data' => $data]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    public function listAssignablePersonnel(): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONSOLE_ROLES);

            $rows = $this->feedbackService->listAssignablePersonnel();
            Response::json(['data' => $rows]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    public function listAssignableRoles(): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONSOLE_ROLES);

            $rows = $this->feedbackService->listAssignableRoles();
            Response::json(['data' => $rows]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    public function listUsers(): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONFIG_ROLES);

            $stmt = $this->db->query(
                "SELECT u.id,
                        u.name,
                        u.first_name,
                        u.last_name,
                        u.email,
                        u.ad_username,
                        u.role,
                        u.employee_number,
                        u.department_name,
                        u.position_title,
                        u.office_location,
                        u.can_assign_cases,
                        u.is_active
                 FROM users u
                 WHERE u.role IN ('hr', 'manager', 'officer', 'ethics')
                 ORDER BY u.name ASC, u.email ASC"
            );
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $userIds = array_values(array_filter(array_map(
                static fn(array $row): string => (string) ($row['id'] ?? ''),
                $users
            ), static fn(string $id): bool => $id !== ''));

            $roleMap = $this->loadUserRoleMap($userIds);
            foreach ($users as &$user) {
                $userId = (string) ($user['id'] ?? '');
                $roles = $roleMap[$userId] ?? [];
                $user['assigned_role_ids'] = array_map(
                    static fn(array $role): string => (string) $role['id'],
                    $roles
                );
                $user['assigned_role_names'] = array_map(
                    static fn(array $role): string => (string) $role['name'],
                    $roles
                );
                $user['assigned_role_id'] = $user['assigned_role_ids'][0] ?? null;
                $user['assigned_role_name'] = $user['assigned_role_names'][0] ?? null;
            }
            unset($user);

            Response::json(['data' => $users]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    public function createUser(): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONFIG_ROLES);

            $payload = Request::input();
            $name = trim((string) ($payload['name'] ?? ''));
            $email = strtolower(trim((string) ($payload['email'] ?? '')));
            $assignedRoleIds = $this->normalizeRoleAssignmentIds($payload);
            $rawRole = trim((string) ($payload['role'] ?? ''));
            $role = $rawRole !== '' ? $this->normalizeRole($rawRole) : Authorization::ROLE_HR;
            $canAssignCases = (int) (!empty($payload['can_assign_cases']) ? 1 : 0);
            $isActive = (int) (!empty($payload['is_active']) ? 1 : 0);

            if ($name === '') {
                throw new ValidationException('User name is required.', 400);
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException('A valid email address is required.', 400);
            }

            $parts = $this->splitName($name);
            $placeholderPasswordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            $userId = self::generateUuid();
            $stmt = $this->db->prepare(
                'INSERT INTO users (
                    id,
                    name,
                    first_name,
                    last_name,
                    email,
                    ad_username,
                    password_hash,
                    role,
                    can_assign_cases,
                    is_active,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
            );
            $stmt->execute([
                $userId,
                $name,
                $parts['first_name'],
                $parts['last_name'],
                $email,
                $placeholderPasswordHash,
                $role,
                $canAssignCases,
                $isActive,
            ]);

            $this->syncUserRoleAssignments($userId, $assignedRoleIds);

            Response::json(['message' => 'User created successfully.'], 201);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        } catch (\Throwable $e) {
            Response::json(['error' => 'Failed to create user.'], 500);
        }
    }

    public function updateUser(array $params): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONFIG_ROLES);

            $userId = trim((string) ($params['id'] ?? ''));
            if ($userId === '') {
                throw new ValidationException('User id is required.', 400);
            }

            $payload = Request::input();
            $name = trim((string) ($payload['name'] ?? ''));
            $email = strtolower(trim((string) ($payload['email'] ?? '')));
            $assignedRoleIds = $this->normalizeRoleAssignmentIds($payload);
            $rawRole = trim((string) ($payload['role'] ?? ''));
            $canAssignCases = (int) (!empty($payload['can_assign_cases']) ? 1 : 0);
            $isActive = (int) (!empty($payload['is_active']) ? 1 : 0);

            if ($name === '') {
                throw new ValidationException('User name is required.', 400);
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException('A valid email address is required.', 400);
            }

            $role = $this->resolveUserConsoleRole($userId, $rawRole);

            $parts = $this->splitName($name);

            $sql = 'UPDATE users
                    SET name = ?,
                        first_name = ?,
                        last_name = ?,
                        email = ?,
                        role = ?,
                        can_assign_cases = ?,
                        is_active = ?,
                        updated_at = CURRENT_TIMESTAMP';
            $values = [
                $name,
                $parts['first_name'],
                $parts['last_name'],
                $email,
                $role,
                $canAssignCases,
                $isActive,
            ];

            $sql .= ' WHERE id = ?';
            $values[] = $userId;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            $this->syncUserRoleAssignments($userId, $assignedRoleIds);

            Response::json(['message' => 'User updated successfully.']);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        } catch (\Throwable $e) {
            Response::json(['error' => 'Failed to update user.'], 500);
        }
    }

    public function deleteUser(array $params): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONFIG_ROLES);

            $userId = trim((string) ($params['id'] ?? ''));
            if ($userId === '') {
                throw new ValidationException('User id is required.', 400);
            }

            $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$userId]);

            if ((int) $stmt->rowCount() < 1) {
                Response::json(['error' => 'User not found.'], 404);
            }

            Response::json(['message' => 'User deleted successfully.']);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        } catch (\Throwable $e) {
            Response::json(['error' => 'Failed to delete user.'], 500);
        }
    }

    private function normalizeRole(mixed $role): string
    {
        $normalized = strtolower(trim((string) $role));
        if (!in_array($normalized, Authorization::CONSOLE_ROLES, true)) {
            throw new ValidationException('A valid console role is required.', 400);
        }

        return $normalized;
    }

    private function resolveUserConsoleRole(string $userId, string $rawRole): string
    {
        if ($rawRole !== '') {
            return $this->normalizeRole($rawRole);
        }

        $roleStmt = $this->db->prepare('SELECT role FROM users WHERE id = ?');
        $roleStmt->execute([$userId]);
        $storedRole = strtolower(trim((string) ($roleStmt->fetchColumn() ?: Authorization::ROLE_HR)));

        if (!in_array($storedRole, Authorization::CONSOLE_ROLES, true)) {
            return Authorization::ROLE_HR;
        }

        return $storedRole;
    }

    private function syncUserRoleAssignments(string $userId, array $roleIds): void
    {
        $deleteStmt = $this->db->prepare('DELETE FROM user_roles WHERE user_id = ?');
        $deleteStmt->execute([$userId]);

        if ($roleIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $roleStmt = $this->db->prepare("SELECT id FROM assignment_roles WHERE is_active = 1 AND id IN ({$placeholders})");
        $roleStmt->execute($roleIds);
        $validRoleIds = array_map(
            static fn(array $row): string => (string) $row['id'],
            $roleStmt->fetchAll(PDO::FETCH_ASSOC)
        );

        if (count($validRoleIds) !== count($roleIds)) {
            throw new ValidationException('One or more selected role assignments are invalid.', 400);
        }

        $insertStmt = $this->db->prepare(
            'INSERT INTO user_roles (id, user_id, role_id, created_at)
             VALUES (?, ?, ?, CURRENT_TIMESTAMP)'
        );

        foreach ($roleIds as $roleId) {
            $insertStmt->execute([self::generateUuid(), $userId, $roleId]);
        }
    }

    private function normalizeRoleAssignmentIds(array $payload): array
    {
        $raw = $payload['assigned_role_ids'] ?? null;
        if (!is_array($raw)) {
            $single = trim((string) ($payload['assigned_role_id'] ?? ''));
            $raw = $single !== '' ? [$single] : [];
        }

        return array_values(array_filter(array_unique(array_map(
            static fn(mixed $value): string => trim((string) $value),
            $raw
        )), static fn(string $value): bool => $value !== ''));
    }

    /** @param string[] $userIds @return array<string, array<int, array{id:string,name:string}>> */
    private function loadUserRoleMap(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT ur.user_id, ar.id AS role_id, ar.name AS role_name
             FROM user_roles ur
             INNER JOIN assignment_roles ar ON ar.id = ur.role_id
             WHERE ur.user_id IN ({$placeholders})
             ORDER BY ar.name ASC"
        );
        $stmt->execute($userIds);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $row) {
            $uid = (string) ($row['user_id'] ?? '');
            if ($uid === '') {
                continue;
            }
            if (!isset($map[$uid])) {
                $map[$uid] = [];
            }
            $map[$uid][] = [
                'id' => (string) ($row['role_id'] ?? ''),
                'name' => (string) ($row['role_name'] ?? ''),
            ];
        }

        return $map;
    }

    /** @return array{first_name:?string,last_name:?string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if ($parts === []) {
            return ['first_name' => null, 'last_name' => null];
        }

        $firstName = array_shift($parts);
        $lastName = $parts !== [] ? implode(' ', $parts) : null;

        return [
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
        ];
    }

    private static function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    }
}
