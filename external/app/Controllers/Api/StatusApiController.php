<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Container;
use App\Core\Response;
use App\Repositories\StatusRepository;

final class StatusApiController
{
    private StatusRepository $statusRepository;

    public function __construct()
    {
        $this->statusRepository = Container::get('statusRepository');
    }

    
    public function listActive(array $params = []): void
    {
        Response::json(['data' => $this->statusRepository->getActive()]);
    }
}
