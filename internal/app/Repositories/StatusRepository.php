<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Status;
use PDO;

final class StatusRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    private static function generateUuid(): string
    {
        return sprintf('%08x-%04x-%04x-%04x-%012x',
            random_int(0, 0xFFFFFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0x0FFF) | 0x4000,
            random_int(0, 0x3FFF) | 0x8000,
            random_int(0, 0xFFFFFFFFFFFF)
        );
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM statuses ORDER BY sort_order ASC, name ASC');
        return array_map(
            static fn(array $row): array => Status::fromRow($row)->toArray(),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function getActive(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM statuses WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');
        return array_map(
            static fn(array $row): array => Status::fromRow($row)->toArray(),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM statuses WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Status::fromRow($row)->toArray() : null;
    }

    public function create(string $name, int $sortOrder = 0, ?string $actorUserId = null): string
    {
        $id = self::generateUuid();
        $stmt = $this->pdo->prepare(
            'INSERT INTO statuses (id, name, is_active, created_by_user_id, updated_by_user_id, sort_order, created_at, updated_at) VALUES (?, ?, 1, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$id, $name, $actorUserId, $actorUserId, $sortOrder]);
        return $id;
    }

    public function update(string $id, string $name, bool $isActive, int $sortOrder, ?string $actorUserId = null): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE statuses SET name = ?, is_active = ?, sort_order = ?, updated_by_user_id = ?, updated_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$name, $isActive ? 1 : 0, $sortOrder, $actorUserId, $id]);
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM statuses WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
