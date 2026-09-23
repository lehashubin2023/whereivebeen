<?php

namespace App\DTOs\Statistic;

use App\DTOs\DTOContract;
use App\Enums\Statistic\StatisticBucketEnum;
use App\Enums\Statistic\StatisticCounterEnum;
use Illuminate\Http\Request;

class StatisticEntryDTO implements DTOContract
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        private readonly StatisticBucketEnum $bucket,
        private readonly StatisticCounterEnum $counter,
        private readonly string $entryKey,
        private readonly int $value,
        private readonly ?array $meta,
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
        $meta = $data['meta'] ?? null;

        return new self(
            bucket: StatisticBucketEnum::from((int) ($data['bucket'] ?? 0)),
            counter: StatisticCounterEnum::from((int) ($data['counter'] ?? 0)),
            entryKey: (string) ($data['entry_key'] ?? ''),
            value: (int) ($data['value'] ?? 0),
            meta: is_array($meta) && $meta !== [] ? $meta : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'bucket' => $this->bucket->value,
            'counter' => $this->counter->value,
            'entry_key' => $this->entryKey,
            'value' => $this->value,
            'meta' => $this->meta === null ? null : json_encode($this->meta),
        ];
    }
}
