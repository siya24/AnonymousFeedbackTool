<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Authorization;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AssignmentRoleRepository;
use App\Repositories\FeedbackRepository;

final class HrRoleApiController
{
    private Authorization $auth;
    private AssignmentRoleRepository $roleRepository;
    private FeedbackRepository $feedbackRepository;

    public function __construct()
    {
        $this->auth = Container::get('auth');
        $this->roleRepository = Container::get('assignmentRoleRepository');
        $this->feedbackRepository = Container::get('feedbackRepository');
    }

    private function buildConfigReference(string $entityId): string
    {
        return 'CFG-ROLE-' . strtoupper(substr($entityId, 0, 8));
    }

    public function listAll(array $params = []): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONSOLE_ROLES);
            Response::json(['data' => $this->roleRepository->getAll()]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    public function getById(array $params): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONSOLE_ROLES);

            $id = trim((string) ($params['id'] ?? ''));
            if ($id === '') {
                Response::json(['error' => 'Invalid role ID'], 400);
            }

            $role = $this->roleRepository->findById($id);
            if (!$role) {
                Response::json(['error' => 'Role not found'], 404);
            }

            Response::json(['data' => $role]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    public function create(array $params = []): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONFIG_ROLES);

            $input = Request::input();
            $name = trim((string) ($input['name'] ?? ''));
            $sortOrder = (int) ($input['sort_order'] ?? 0);

            if ($name === '') {
                Response::json(['error' => 'Role name is required'], 422);
            }
            if (mb_strlen($name) > 120) {
                Response::json(['error' => 'Role name must be 120 characters or less'], 422);
            }

            $actorUserId = $this->auth->getUserId();
            $id = $this->roleRepository->create($name, $sortOrder, $actorUserId);

            $this->feedbackRepository->logAudit(
                'hr',
                'role_created',
                $this->buildConfigReference($id),
                json_encode(['role_id' => $id, 'name' => $name, 'sort_order' => $sortOrder]),
                $actorUserId
            );

            Response::json(['data' => $this->roleRepository->findById($id)], 201);
        } catch (\Throwable $e) {
            $code = (int) ($e->getCode() ?: 400);
            if ($code < 400 || $code > 599) {
                $code = 400;
            }
            $msg = str_contains($e->getMessage(), '1062') ? 'A role with that name already exists' : $e->getMessage();
            Response::json(['error' => $msg], $code);
        }
    }

    public function update(array $params): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONFIG_ROLES);

            $id = trim((string) ($params['id'] ?? ''));
            $input = Request::input();
            $name = trim((string) ($input['name'] ?? ''));
            $isActive = (bool) ($input['is_active'] ?? true);
            $sortOrder = (int) ($input['sort_order'] ?? 0);

            if ($id === '') {
                Response::json(['error' => 'Invalid role ID'], 400);
            }
            if ($name === '') {
                Response::json(['error' => 'Role name is required'], 422);
            }
            if (mb_strlen($name) > 120) {
                Response::json(['error' => 'Role name must be 120 characters or less'], 422);
            }
            if (!$this->roleRepository->findById($id)) {
                Response::json(['error' => 'Role not found'], 404);
            }

            $actorUserId = $this->auth->getUserId();
            $this->roleRepository->update($id, $name, $isActive, $sortOrder, $actorUserId);

            $this->feedbackRepository->logAudit(
                'hr',
                'role_updated',
                $this->buildConfigReference($id),
                json_encode(['role_id' => $id, 'name' => $name, 'is_active' => $isActive, 'sort_order' => $sortOrder]),
                $actorUserId
            );

            Response::json(['data' => $this->roleRepository->findById($id)]);
        } catch (\Throwable $e) {
            $code = (int) ($e->getCode() ?: 400);
            if ($code < 400 || $code > 599) {
                $code = 400;
            }
            $msg = str_contains($e->getMessage(), '1062') ? 'A role with that name already exists' : $e->getMessage();
            Response::json(['error' => $msg], $code);
        }
    }

    public function delete(array $params): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONFIG_ROLES);

            $id = trim((string) ($params['id'] ?? ''));
            if ($id === '') {
                Response::json(['error' => 'Invalid role ID'], 400);
            }
            if (!$this->roleRepository->findById($id)) {
                Response::json(['error' => 'Role not found'], 404);
            }

            $actorUserId = $this->auth->getUserId();
            $this->roleRepository->delete($id);

            $this->feedbackRepository->logAudit(
                'hr',
                'role_deleted',
                $this->buildConfigReference($id),
                json_encode(['role_id' => $id]),
                $actorUserId
            );

            Response::json(['message' => 'Role deleted']);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }
}
