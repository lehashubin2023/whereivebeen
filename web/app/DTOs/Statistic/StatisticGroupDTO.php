<?php

namespace App\DTOs\Statistic;

use App\DTOs\DTOContract;
use Illuminate\Http\Request;

class StatisticGroupDTO implements DTOContract
{
    /**
     * @param  array<int, array<string, mixed>>  $tables
     */
    public function __construct(
        private readonly string $slug,
        private readonly string $label,
        private readonly int $total,
        private readonly array $tables,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->all());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            slug: (string) ($data['slug'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            total: (int) ($data['total'] ?? 0),
            tables: (array) ($data['tables'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label,
            'total' => $this->total,
            'tables' => $this->tables,
        ];
    }
}
