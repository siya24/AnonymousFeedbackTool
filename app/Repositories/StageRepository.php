<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Stage;
use PDO;

final class StageRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM stages ORDER BY sort_order ASC, name ASC');
        return array_map(
            static fn(array $row): array => Stage::fromRow($row)->toArray(),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function getActive(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM stages WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');
        return array_map(
            static fn(array $row): array => Stage::fromRow($row)->toArray(),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stages WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Stage::fromRow($row)->toArray() : null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stages WHERE name = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Stage::fromRow($row)->toArray() : null;
    }

    public function create(string $name, int $sortOrder = 0): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO stages (name, is_active, sort_order, created_at, updated_at) VALUES (?, 1, ?, NOW(), NOW())'
        );
        $stmt->execute([$name, $sortOrder]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, bool $isActive, int $sortOrder): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE stages SET name = ?, is_active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$name, $isActive ? 1 : 0, $sortOrder, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM stages WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
