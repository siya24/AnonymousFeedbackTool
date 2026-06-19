<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Container;
use App\Core\Response;
use App\Repositories\ProvinceRepository;

final class ProvinceApiController
{
    private ProvinceRepository $provinceRepository;

    public function __construct()
    {
        $this->provinceRepository = Container::get('provinceRepository');
    }

    public function listActive(array $params = []): void
    {
        Response::json(['data' => $this->provinceRepository->getActive()]);
    }
}

