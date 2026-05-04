<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Authorization;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\FeedbackRepository;
use App\Repositories\StatusRepository;

final class HrStatusApiController
{
    private Authorization $auth;
    private StatusRepository $statusRepository;
    private FeedbackRepository $feedbackRepository;

    public function __construct()
    {
        $this->auth = Container::get('auth');
        $this->statusRepository = Container::get('statusRepository');
        $this->feedbackRepository = Container::get('feedbackRepository');
    }

    private function buildConfigReference(string $prefix, string $entityId): string
    {
        return $prefix . '-' . strtoupper(substr($entityId, 0, 8));
    }

    public function listAll(array $params = []): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONFIG_ROLES);
            Response::json(['data' => $this->statusRepository->getAll()]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    public function getById(array $params): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireAnyRole(Authorization::CONFIG_ROLES);

            $id = trim((string) ($params['id'] ?? ''));
            if ($id === '') {
                Response::json(['error' => 'Invalid status ID'], 400);
            }

            $status = $this->statusRepository->findById($id);
            if (!$status) {
                Response::json(['error' => 'Status not found'], 404);
            }

            Response::json(['data' => $status]);
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
                Response::json(['error' => 'Status name is required'], 422);
            }
            if (mb_strlen($name) > 120) {
                Response::json(['error' => 'Status name must be 120 characters or less'], 422);
            }

            $actorUserId = $this->auth->getUserId();
            $id = $this->statusRepository->create($name, $sortOrder, $actorUserId);
            $this->feedbackRepository->logAudit(
                'hr',
                'status_created',
                $this->buildConfigReference('CFG-STS', $id),
                json_encode(['status_id' => $id, 'name' => $name, 'sort_order' => $sortOrder]),
                $actorUserId
            );
            Response::json(['data' => $this->statusRepository->findById($id)], 201);
        } catch (\Throwable $e) {
            $code = (int) ($e->getCode() ?: 400);
            if ($code < 400 || $code > 599) {
                $code = 400;
            }
            $msg = str_contains($e->getMessage(), '1062') ? 'A status with that name already exists' : $e->getMessage();
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
                Response::json(['error' => 'Invalid status ID'], 400);
            }
            if ($name === '') {
                Response::json(['error' => 'Status name is required'], 422);
            }
            if (mb_strlen($name) > 120) {
                Response::json(['error' => 'Status name must be 120 characters or less'], 422);
            }

            if (!$this->statusRepository->findById($id)) {
                Response::json(['error' => 'Status not found'], 404);
            }

            $actorUserId = $this->auth->getUserId();
            $this->statusRepository->update($id, $name, $isActive, $sortOrder, $actorUserId);
            $this->feedbackRepository->logAudit(
                'hr',
                'status_updated',
                $this->buildConfigReference('CFG-STS', $id),
                json_encode(['status_id' => $id, 'name' => $name, 'is_active' => $isActive, 'sort_order' => $sortOrder]),
                $actorUserId
            );
            Response::json(['data' => $this->statusRepository->findById($id)]);
        } catch (\Throwable $e) {
            $code = (int) ($e->getCode() ?: 400);
            if ($code < 400 || $code > 599) {
                $code = 400;
            }
            $msg = str_contains($e->getMessage(), '1062') ? 'A status with that name already exists' : $e->getMessage();
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
                Response::json(['error' => 'Invalid status ID'], 400);
            }

            if (!$this->statusRepository->findById($id)) {
                Response::json(['error' => 'Status not found'], 404);
            }

            $actorUserId = $this->auth->getUserId();
            $this->statusRepository->delete($id);
            $this->feedbackRepository->logAudit(
                'hr',
                'status_deleted',
                $this->buildConfigReference('CFG-STS', $id),
                json_encode(['status_id' => $id]),
                $actorUserId
            );
            Response::json(['message' => 'Status deleted']);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }
}
