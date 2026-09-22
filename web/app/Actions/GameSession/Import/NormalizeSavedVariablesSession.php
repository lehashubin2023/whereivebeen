<?php

namespace App\Actions\GameSession\Import;

use App\DTOs\GameSession\SavedVariablesSessionDTO;
use App\Enums\GameSession\SavedVariablesSkipReasonEnum;
use App\Models\GameSession;
use App\Support\GameSession\SavedVariables\SessionSpool;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class NormalizeSavedVariablesSession
{
    private const HEADER_MAP = [
        'schema' => 'schema',
        'continuesFrom' => 'continuesFrom',
        'started' => 'started',
        'ended' => 'ended',
        'char' => 'char',
        'realm' => 'realm',
        'faction' => 'faction',
        'class' => 'class',
        'level' => 'level',
    ];

    public function reject(SavedVariablesSessionDTO $session): ?SavedVariablesSkipReasonEnum
    {
        if ($session->sessionId <= 0) {
            return SavedVariablesSkipReasonEnum::NO_SESSION_ID;
        }

        if ($session->pointsCount === 0) {
            return SavedVariablesSkipReasonEnum::NO_POINTS;
        }

        if ($session->character() === null || $session->realm() === null) {
            return SavedVariablesSkipReasonEnum::NO_CHARACTER;
        }

        if ($session->startedAt() === null) {
            return SavedVariablesSkipReasonEnum::NO_START_TIME;
        }

        if ($this->spoolSize($session) > GameSession::MAX_IMPORT_SIZE) {
            return SavedVariablesSkipReasonEnum::TOO_LARGE;
        }

        return null;
    }

    public function exec(SavedVariablesSessionDTO $session, string $batchUuid): string
    {
        $target = sprintf('%s/%s/%d.export.json', SessionSpool::DIR, $batchUuid, $session->sessionId);

        Storage::disk(SessionSpool::DISK)->makeDirectory(dirname($target));

        $out = fopen(Storage::disk(SessionSpool::DISK)->path($target), 'wb');

        if ($out === false) {
            throw new RuntimeException('Unable to open the export file for writing');
        }

        $spool = fopen(Storage::disk(SessionSpool::DISK)->path($session->spoolPath), 'rb');

        if ($spool === false) {
            fclose($out);

            throw new RuntimeException('Unable to read the spooled points');
        }

        try {
            fwrite($out, rtrim($this->envelope($session), '}').',"points":[');

            $first = true;

            while (($line = fgets($spool)) !== false) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                fwrite($out, $first ? $line : ','.$line);
                $first = false;
            }

            fwrite($out, ']}');
        } finally {
            fclose($spool);
            fclose($out);
        }

        return $target;
    }

    private function envelope(SavedVariablesSessionDTO $session): string
    {
        $envelope = ['sessionId' => $session->sessionId];

        foreach (self::HEADER_MAP as $source => $target) {
            if (array_key_exists($source, $session->header)) {
                $envelope[$target] = $session->header[$source];
            }
        }

        $toc = $session->header['toc'] ?? null;

        if (is_numeric($toc)) {
            $envelope['version'] = (int) $toc;
        }

        return (string) json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function spoolSize(SavedVariablesSessionDTO $session): int
    {
        $disk = Storage::disk(SessionSpool::DISK);

        return $disk->exists($session->spoolPath) ? $disk->size($session->spoolPath) : 0;
    }
}
