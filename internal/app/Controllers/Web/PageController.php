<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Response;

final class PageController
{
    public function home(): void
    {
        Response::view('pages/hr_dashboard', ['title' => 'HR Dashboard']);
    }

    public function hr(): void
    {
        Response::view('pages/hr', ['title' => 'HR Console']);
    }

    public function hrLogin(): void
    {
        Response::view('pages/hr_login', [
            'title' => 'HR Login',
            'hideHrAuthNav' => true,
        ]);
    }

    public function hrCase(array $params = []): void
    {
        $reference = strtoupper(trim((string) ($params['reference'] ?? '')));
        Response::view('pages/hr_case', [
            'title' => 'Update Feedback Case',
            'reference' => $reference,
        ]);
    }

    public function hrDashboard(): void
    {
        header('Location: /', true, 302);
        exit;
    }

    public function hrReports(): void
    {
        Response::view('pages/hr_reports', ['title' => 'Case Reports']);
    }

    public function hrCategories(): void
    {
        Response::view('pages/hr_categories', ['title' => 'Manage Categories']);
    }

    public function hrStatuses(): void
    {
        Response::view('pages/hr_statuses', ['title' => 'Manage Statuses']);
    }

    public function hrStages(): void
    {
        Response::view('pages/hr_stages', ['title' => 'Manage Stages']);
    }

    public function hrRoles(): void
    {
        Response::view('pages/hr_roles', ['title' => 'Manage Roles']);
    }

    public function hrUsers(): void
    {
        Response::view('pages/hr_users', ['title' => 'Manage Users']);
    }

    public function hrPersonnelRoles(): void
    {
        Response::view('pages/hr_users', ['title' => 'Manage Users']);
    }
}
