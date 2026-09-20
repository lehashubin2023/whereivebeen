<?php

namespace App\Actions\Map;

use App\Models\Map;

class RegisterMissingMaps
{
    private const CHUNK = 500;

    private const NAME_PREFIX = 'Zone ';

    /**
     * @param  list<int>  $mapIds
     * @return list<int>
     */
    public function exec(array $mapIds): array
    {
        $ids = array_values(array_unique(array_filter(
            $mapIds,
            fn (int $id) => $id > 0 && $id <= Map::MAX_ID
        )));

        if ($ids === []) {
            return [];
        }

        /** @var list<int> $existing */
        $existing = Map::query()->whereIn('id', $ids)->pluck('id')->all();

        $missing = array_values(array_diff($ids, $existing));

        if ($missing === []) {
            return [];
        }

        sort($missing);

        foreach (array_chunk($missing, self::CHUNK) as $chunk) {
            Map::query()->insertOrIgnore(array_map(
                fn (int $id) => [
                    'id' => $id,
                    'name' => self::NAME_PREFIX.$id,
                    'auto_added' => true,
                ],
                $chunk
            ));
        }

        return $missing;
    }
}
