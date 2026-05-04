<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Authorization;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\FeedbackRepository;
use App\Repositories\StageRepository;

final class HrStageApiController
{
    private Authorization $auth;
    private StageRepository $stageRepository;
    private FeedbackRepository $feedbackRepository;

    public function __construct()
    {
        $this->auth = Container::get('auth');
        $this->stageRepository = Container::get('stageRepository');
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
            Response::json(['data' => $this->stageRepository->getAll()]);
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
                Response::json(['error' => 'Invalid stage ID'], 400);
            }

            $stage = $this->stageRepository->findById($id);
            if (!$stage) {
                Response::json(['error' => 'Stage not found'], 404);
            }

            Response::json(['data' => $stage]);
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
                Response::json(['error' => 'Stage name is required'], 422);
            }
            if (mb_strlen($name) > 120) {
                Response::json(['error' => 'Stage name must be 120 characters or less'], 422);
            }

            $actorUserId = $this->auth->getUserId();
            $id = $this->stageRepository->create($name, $sortOrder, $actorUserId);
            $this->feedbackRepository->logAudit(
                'hr',
                'stage_created',
                $this->buildConfigReference('CFG-STG', $id),
                json_encode(['stage_id' => $id, 'name' => $name, 'sort_order' => $sortOrder]),
                $actorUserId
            );
            Response::json(['data' => $this->stageRepository->findById($id)], 201);
        } catch (\Throwable $e) {
            $code = (int) ($e->getCode() ?: 400);
            if ($code < 400 || $code > 599) {
                $code = 400;
            }
            $msg = str_contains($e->getMessage(), '1062') ? 'A stage with that name already exists' : $e->getMessage();
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
                Response::json(['error' => 'Invalid stage ID'], 400);
            }
            if ($name === '') {
                Response::json(['error' => 'Stage name is required'], 422);
            }
            if (mb_strlen($name) > 120) {
                Response::json(['error' => 'Stage name must be 120 characters or less'], 422);
            }

            if (!$this->stageRepository->findById($id)) {
                Response::json(['error' => 'Stage not found'], 404);
            }

            $actorUserId = $this->auth->getUserId();
            $this->stageRepository->update($id, $name, $isActive, $sortOrder, $actorUserId);
            $this->feedbackRepository->logAudit(
                'hr',
                'stage_updated',
                $this->buildConfigReference('CFG-STG', $id),
                json_encode(['stage_id' => $id, 'name' => $name, 'is_active' => $isActive, 'sort_order' => $sortOrder]),
                $actorUserId
            );
            Response::json(['data' => $this->stageRepository->findById($id)]);
        } catch (\Throwable $e) {
            $code = (int) ($e->getCode() ?: 400);
            if ($code < 400 || $code > 599) {
                $code = 400;
            }
            $msg = str_contains($e->getMessage(), '1062') ? 'A stage with that name already exists' : $e->getMessage();
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
                Response::json(['error' => 'Invalid stage ID'], 400);
            }

            if (!$this->stageRepository->findById($id)) {
                Response::json(['error' => 'Stage not found'], 404);
            }

            $actorUserId = $this->auth->getUserId();
            $this->stageRepository->delete($id);
            $this->feedbackRepository->logAudit(
                'hr',
                'stage_deleted',
                $this->buildConfigReference('CFG-STG', $id),
                json_encode(['stage_id' => $id]),
                $actorUserId
            );
            Response::json(['message' => 'Stage deleted']);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }
}
