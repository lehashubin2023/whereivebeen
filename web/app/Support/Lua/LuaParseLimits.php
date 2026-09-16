<?php

namespace App\Support\Lua;

final readonly class LuaParseLimits
{
    public const DEFAULT_MAX_BYTES = 33554432;

    public function __construct(
        public int $maxBytes = self::DEFAULT_MAX_BYTES,
        public int $maxDepth = 12,
        public int $maxStringLength = 4096,
        public int $maxTableEntries = 500000,
        public int $maxSessions = 500,
        public int $maxPointsPerSession = 500000,
    ) {}
}
