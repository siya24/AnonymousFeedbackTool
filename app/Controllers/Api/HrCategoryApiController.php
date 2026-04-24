<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Authorization;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CategoryRepository;

final class HrCategoryApiController
{
    private Authorization $auth;
    private CategoryRepository $categoryRepository;

    public function __construct()
    {
        $this->auth = Container::get('auth');
        $this->categoryRepository = Container::get('categoryRepository');
    }

    public function listAll(array $params = []): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireRole(Authorization::ROLE_HR);
            Response::json(['data' => $this->categoryRepository->getAll()]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    public function getById(array $params): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireRole(Authorization::ROLE_HR);

            $id = (int) ($params['id'] ?? 0);
            if ($id <= 0) {
                Response::json(['error' => 'Invalid category ID'], 400);
            }

            $category = $this->categoryRepository->findById($id);
            if (!$category) {
                Response::json(['error' => 'Category not found'], 404);
            }

            Response::json(['data' => $category]);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }

    public function create(array $params = []): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireRole(Authorization::ROLE_HR);

            $input = Request::input();
            $name = trim((string) ($input['name'] ?? ''));
            $sortOrder = (int) ($input['sort_order'] ?? 0);

            if ($name === '') {
                Response::json(['error' => 'Category name is required'], 422);
            }
            if (mb_strlen($name) > 120) {
                Response::json(['error' => 'Category name must be 120 characters or less'], 422);
            }

            $id = $this->categoryRepository->create($name, $sortOrder);
            Response::json(['data' => $this->categoryRepository->findById($id)], 201);
        } catch (\Throwable $e) {
            $code = (int) ($e->getCode() ?: 400);
            if ($code < 400 || $code > 599) {
                $code = 400;
            }
            $msg = str_contains($e->getMessage(), '1062') ? 'A category with that name already exists' : $e->getMessage();
            Response::json(['error' => $msg], $code);
        }
    }

    public function update(array $params): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireRole(Authorization::ROLE_HR);

            $id = (int) ($params['id'] ?? 0);
            $input = Request::input();
            $name = trim((string) ($input['name'] ?? ''));
            $isActive = (bool) ($input['is_active'] ?? true);
            $sortOrder = (int) ($input['sort_order'] ?? 0);

            if ($id <= 0) {
                Response::json(['error' => 'Invalid category ID'], 400);
            }
            if ($name === '') {
                Response::json(['error' => 'Category name is required'], 422);
            }
            if (mb_strlen($name) > 120) {
                Response::json(['error' => 'Category name must be 120 characters or less'], 422);
            }

            if (!$this->categoryRepository->findById($id)) {
                Response::json(['error' => 'Category not found'], 404);
            }

            $this->categoryRepository->update($id, $name, $isActive, $sortOrder);
            Response::json(['data' => $this->categoryRepository->findById($id)]);
        } catch (\Throwable $e) {
            $code = (int) ($e->getCode() ?: 400);
            if ($code < 400 || $code > 599) {
                $code = 400;
            }
            $msg = str_contains($e->getMessage(), '1062') ? 'A category with that name already exists' : $e->getMessage();
            Response::json(['error' => $msg], $code);
        }
    }

    public function delete(array $params): void
    {
        try {
            $this->auth->authenticate();
            $this->auth->requireRole(Authorization::ROLE_HR);

            $id = (int) ($params['id'] ?? 0);
            if ($id <= 0) {
                Response::json(['error' => 'Invalid category ID'], 400);
            }

            if (!$this->categoryRepository->findById($id)) {
                Response::json(['error' => 'Category not found'], 404);
            }

            $this->categoryRepository->delete($id);
            Response::json(['message' => 'Category deleted']);
        } catch (\RuntimeException $e) {
            $code = (int) ($e->getCode() ?: 400);
            Response::json(['error' => $e->getMessage()], $code);
        }
    }
}
