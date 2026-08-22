<?php

namespace App\Actions\Map;

use App\Exceptions\Map\InvalidUiMapCsvException;

class ParseUiMapCsv
{
    public function exec(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidUiMapCsvException("UiMap file not found: {$path}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new InvalidUiMapCsvException("UiMap file is not readable: {$path}");
        }

        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);

            throw new InvalidUiMapCsvException('UiMap CSV is empty');
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($header[0] ?? ''));
        $cols = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $idIdx = array_search('id', $cols, true);
        $nameIdx = array_search('name_lang', $cols, true);

        if ($idIdx === false || $nameIdx === false) {
            fclose($handle);

            throw new InvalidUiMapCsvException('UiMap CSV must contain ID and Name_lang columns');
        }

        $rows = [];
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (isset($row[$idIdx], $row[$nameIdx])) {
                $rows[] = ['id' => $row[$idIdx], 'name' => $row[$nameIdx]];
            }
        }
        fclose($handle);

        return $rows;
    }
}
