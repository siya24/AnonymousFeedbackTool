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

    public function hrCase(array $params = []): void
    {
        $reference = strtoupper(trim((string) ($params['reference'] ?? '')));
        Response::view('pages/hr_case', [
            'title' => 'Update Feedback Case',
            'reference' => $reference,
        ]);
    }

    public function hrDashboard(array $params = []): void
    {
        Response::view('pages/hr_dashboard', ['title' => 'HR Dashboard']);
    }

    public function hrReports(array $params = []): void
    {
        Response::view('pages/hr_reports', ['title' => 'Case Reports']);
    }

    public function hrCategories(array $params = []): void
    {
        Response::view('pages/hr_categories', ['title' => 'Manage Categories']);
    }

    public function hrStatuses(array $params = []): void
    {
        Response::view('pages/hr_statuses', ['title' => 'Manage Statuses']);
    }

    public function hrStages(array $params = []): void
    {
        Response::view('pages/hr_stages', ['title' => 'Manage Stages']);
    }

    public function apiDocs(array $params = []): void
    {
        $viewPath = __DIR__ . '/../../Views/pages/api_docs.php';
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        readfile($viewPath);
        exit;
    }

    public function openApiSpec(array $params = []): void
    {
        $specPath = __DIR__ . '/../../../public/api-docs/openapi.json';
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        $origin = rtrim((string) (getenv('APP_BASE_URL') ?: 'http://localhost:8000'), '/');
        header('Access-Control-Allow-Origin: ' . $origin);
        readfile($specPath);
        exit;
    }
}
