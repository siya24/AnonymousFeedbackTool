<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Response;

final class PageController
{
    public function home(array $params = []): void
    {
        Response::view('pages/home', ['title' => 'Anonymous Feedback']);
    }

    public function hr(array $params = []): void
    {
        Response::view('pages/hr', ['title' => 'HR Console']);
    }
}
