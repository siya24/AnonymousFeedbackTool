<?php declare(strict_types=1);

namespace App\Repositories;

use PDO;

class FeedbackInsightsRepository
{
    public function __construct(private PDO $pdo) {}

    public function listAssignablePersonnel(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, email, role, department_name, position_title, office_location
             FROM users
             WHERE is_active = 1
               AND role IN ('hr', 'officer', 'manager')
             ORDER BY name ASC, email ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listAssignableRoles(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, sort_order
             FROM assignment_roles
             WHERE is_active = 1
             ORDER BY sort_order ASC, name ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getQuarterlyCategoryTrends(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                    CASE
                        WHEN MONTH(r.created_at) >= 4 THEN YEAR(r.created_at)
                        ELSE YEAR(r.created_at) - 1
                    END AS fiscal_year_start,
                    CASE
                        WHEN MONTH(r.created_at) BETWEEN 4 AND 6 THEN 1
                        WHEN MONTH(r.created_at) BETWEEN 7 AND 9 THEN 2
                        WHEN MONTH(r.created_at) BETWEEN 10 AND 12 THEN 3
                        ELSE 4
                    END AS quarter_no,
                    c.name AS category,
                    COUNT(*) AS total_cases
             FROM feedbacks r
             LEFT JOIN categories c ON c.id = r.category_id
             GROUP BY
                CASE
                    WHEN MONTH(r.created_at) >= 4 THEN YEAR(r.created_at)
                    ELSE YEAR(r.created_at) - 1
                END,
                CASE
                    WHEN MONTH(r.created_at) BETWEEN 4 AND 6 THEN 1
                    WHEN MONTH(r.created_at) BETWEEN 7 AND 9 THEN 2
                    WHEN MONTH(r.created_at) BETWEEN 10 AND 12 THEN 3
                    ELSE 4
                END,
                c.name
             ORDER BY fiscal_year_start DESC, quarter_no DESC, c.name ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStatusTotals(): array
    {
        $stmt = $this->pdo->query(
            'SELECT s.name AS status, COUNT(*) AS total
             FROM feedbacks r
             LEFT JOIN statuses s ON s.id = r.status_id
             GROUP BY s.name
             ORDER BY total DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProvinceTotals(): array
    {
        $stmt = $this->pdo->query(
            "SELECT COALESCE(p.name, 'Not specified') AS province,
                    COUNT(*) AS total
             FROM feedbacks r
             LEFT JOIN provinces p ON p.id = r.province_id
             GROUP BY COALESCE(p.name, 'Not specified')
             ORDER BY total DESC, province ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategoryFrequencySummary(): array
    {
        $stmt = $this->pdo->query(
            'SELECT c.name AS category,
                    COUNT(*) AS total_cases,
                    SUM(CASE WHEN s.name NOT LIKE \'%completed%\' THEN 1 ELSE 0 END) AS open_cases
             FROM feedbacks r
             LEFT JOIN statuses s ON s.id = r.status_id
             LEFT JOIN categories c ON c.id = r.category_id
             GROUP BY c.name
             ORDER BY open_cases DESC, total_cases DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
