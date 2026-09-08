<?php

namespace App\DTOs\GameSession;

use App\DTOs\DTOContract;
use Illuminate\Http\Request;

class StatisticTableDTO implements DTOContract
{
    /**
     * @param  array<int, string>  $columns
     * @param  array<int, array<int, string>>  $rows
     */
    public function __construct(
        private readonly string $title,
        private readonly array $columns,
        private readonly array $rows,
        private readonly int $rowsTotal,
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
            title: (string) ($data['title'] ?? ''),
            columns: (array) ($data['columns'] ?? []),
            rows: (array) ($data['rows'] ?? []),
            rowsTotal: (int) ($data['rows_total'] ?? 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'columns' => $this->columns,
            'rows' => $this->rows,
            'rows_total' => $this->rowsTotal,
        ];
    }
}
