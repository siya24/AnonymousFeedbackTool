<?php
declare(strict_types=1);

namespace App\Models;

final class Category
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $isActive,
        public int $sortOrder,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['id'] ?? ''),
            (string) ($row['name'] ?? ''),
            ((int) ($row['is_active'] ?? 0)) === 1,
            (int) ($row['sort_order'] ?? 0),
            (string) ($row['created_at'] ?? ''),
            (string) ($row['updated_at'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->isActive ? 1 : 0,
            'sort_order' => $this->sortOrder,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
