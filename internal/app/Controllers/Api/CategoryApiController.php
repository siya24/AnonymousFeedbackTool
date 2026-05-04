<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Container;
use App\Core\Response;
use App\Repositories\CategoryRepository;

final class CategoryApiController
{
    private CategoryRepository $categoryRepository;

    public function __construct()
    {
        $this->categoryRepository = Container::get('categoryRepository');
    }

    
    public function listActive(array $params = []): void
    {
        Response::json(['data' => $this->categoryRepository->getActive()]);
    }

    
    public function getById(array $params): void
    {
        $id = trim((string) ($params['id'] ?? ''));
        if ($id === '') {
            Response::json(['error' => 'Invalid category ID'], 400);
        }

        $category = $this->categoryRepository->findById($id);
        if (!$category || (int) ($category['is_active'] ?? 0) !== 1) {
            Response::json(['error' => 'Category not found'], 404);
        }

        Response::json(['data' => $category]);
    }
}
