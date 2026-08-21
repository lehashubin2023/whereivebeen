<?php

namespace App\Actions\Map;

use App\Models\Map;

class ImportMaps
{
    private const CHUNK = 500;

    /** Порядок важен: ищем изображение карты по первому подходящему расширению. */
    private const IMAGE_EXTENSIONS = ['png', 'webp', 'jpg', 'jpeg'];

    /**
     * Наполняет таблицу maps из манифеста (id + name) и папки изображений.
     * image_path проставляется, если в $imagesDir лежит файл <id>.<ext>.
     *
     * @param  array<int, array<string, mixed>>  $manifest  [{id, name}, ...] из аддона /wivbn maps или UiMap.db2
     * @return array{total: int, withImage: int, skipped: int}
     */
    public function exec(array $manifest, string $imagesDir, string $urlPrefix): array
    {
        $rows = [];
        $skipped = 0;
        $withImage = 0;

        foreach ($manifest as $entry) {
            $id = filter_var($entry['id'] ?? null, FILTER_VALIDATE_INT);
            $name = isset($entry['name']) && is_string($entry['name']) ? trim($entry['name']) : '';

            // smallint unsigned + непустое имя — иначе строка манифеста некорректна
            if ($id === false || $id <= 0 || $id > 65535 || $name === '') {
                $skipped++;

                continue;
            }

            $imagePath = $this->resolveImagePath($id, $imagesDir, $urlPrefix);
            if ($imagePath !== null) {
                $withImage++;
            }

            $rows[] = [
                'id' => $id,
                'name' => mb_substr($name, 0, 128),
                'image_path' => $imagePath,
            ];
        }

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            Map::upsert($chunk, ['id'], ['name', 'image_path']);
        }

        return ['total' => count($rows), 'withImage' => $withImage, 'skipped' => $skipped];
    }

    private function resolveImagePath(int $id, string $imagesDir, string $urlPrefix): ?string
    {
        $dir = rtrim($imagesDir, "/\\");

        foreach (self::IMAGE_EXTENSIONS as $ext) {
            if (is_file($dir.DIRECTORY_SEPARATOR.$id.'.'.$ext)) {
                return rtrim($urlPrefix, '/').'/'.$id.'.'.$ext;
            }
        }

        return null;
    }
}
