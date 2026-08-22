<?php

namespace App\Actions\Map;

use App\Models\Map;

class ImportMaps
{
    private const CHUNK = 500;

    public function exec(array $rows): array
    {
        $out = [];
        $skipped = 0;

        foreach ($rows as $entry) {
            $id = $this->prepareId($entry);
            $name = $this->prepareName($entry);

            if (! is_int($id) || ! $name) {
                $skipped++;

                continue;
            }

            $out[] = ['id' => $id, 'name' => $name];
        }

        foreach (array_chunk($out, self::CHUNK) as $chunk) {
            Map::upsert($chunk, ['id'], ['name']);
        }

        return ['total' => count($out), 'skipped' => $skipped];
    }

    private function prepareId(array $entry): int|false
    {
        if (! isset($entry['id'])) {
            return false;
        }

        $id = filter_var($entry['id'], FILTER_VALIDATE_INT);

        if ($id === false || $id <= 0 || $id > 65535) {
            return false;
        }

        return $id;
    }

    private function prepareName(array $entry): string|false
    {
        if (! isset($entry['name']) || ! is_string($entry['name']) || ! mb_strlen(trim($entry['name']))) {
            return false;
        }

        return mb_substr(trim($entry['name']), 0, 128);
    }
}
