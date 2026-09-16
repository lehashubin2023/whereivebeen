<?php

namespace App\Support\GameSession\SavedVariables;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SessionSpool
{
    public const DISK = 'local';

    public const DIR = 'imports/spool';

    public const UPLOADS_DIR = 'imports/uploads';

    /**
     * @var resource|null
     */
    private $handle = null;

    private string $path = '';

    public function open(string $batchUuid, int|string $sessionId): self
    {
        $this->path = $this->path($batchUuid, $sessionId);

        Storage::disk(self::DISK)->makeDirectory(dirname($this->path));

        $handle = fopen(Storage::disk(self::DISK)->path($this->path), 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the spool file for writing');
        }

        $this->handle = $handle;

        return $this;
    }

    /**
     * @param  array<string|int, mixed>  $point
     */
    public function write(array $point): void
    {
        if ($this->handle === null) {
            throw new RuntimeException('The spool file is not open');
        }

        fwrite($this->handle, json_encode($point, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }

    public function close(): string
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }

        return $this->path;
    }

    public function path(string $batchUuid, int|string $sessionId): string
    {
        return sprintf('%s/%s/%s.jsonl', self::DIR, $batchUuid, $sessionId);
    }

    public function purge(string $batchUuid): void
    {
        Storage::disk(self::DISK)->deleteDirectory(self::DIR.'/'.$batchUuid);
    }
}
