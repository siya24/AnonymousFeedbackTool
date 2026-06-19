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
}
