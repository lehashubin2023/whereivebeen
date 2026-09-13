<?php

namespace App\Support\Lua;

final readonly class LuaToken
{
    public function __construct(
        public LuaTokenType $type,
        public string|int|float|bool|null $value,
        public int $line,
    ) {}

    public function is(LuaTokenType $type): bool
    {
        return $this->type === $type;
    }
}
