<?php

namespace App\Support\GameSession;

use App\Models\Map;
use App\Models\WayPoint;

/**
 * Аддон видит карты, которых нет в `maps`: инстансы, микро-зоны, карты новых
 * патчей. Точка с таким `mapId` валила весь импорт по внешнему ключу, поэтому
 * неизвестная карта отбрасывается в `null`, а сама точка сохраняется.
 */
class MapIdFilter
{
    /**
     * @param  array<int, true>  $known
     */
    private function __construct(private array $known)
    {
        //
    }

    /**
     * @var array<int, int>
     */
    private array $unknown = [];

    public static function fromDatabase(): self
    {
        /** @var array<int, true> $known */
        $known = Map::query()->pluck('id')->flip()->map(fn () => true)->all();

        return new self($known);
    }

    /**
     * @param  array<int, true>  $known
     */
    public static function fromIds(array $known): self
    {
        return new self($known);
    }

    public function resolve(mixed $mapId): ?int
    {
        if ($mapId === null || $mapId === '') {
            return null;
        }

        $id = (int) $mapId;

        if ($id <= 0 || $id > WayPoint::COODS_FIELD_LENGTH) {
            return null;
        }

        if (isset($this->known[$id])) {
            return $id;
        }

        $this->unknown[$id] = ($this->unknown[$id] ?? 0) + 1;

        return null;
    }

    /**
     * @return array<int, int>
     */
    public function unknown(): array
    {
        return $this->unknown;
    }

    public function hasUnknown(): bool
    {
        return $this->unknown !== [];
    }
}
