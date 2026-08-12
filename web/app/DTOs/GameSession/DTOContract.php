<?php

namespace App\DTOs;

use Illuminate\Http\Request;

interface DTOContract
{
    public static function fromRequest(Request $request): self;
    public static function fromArray(array $data): self;
    public function toArray(): array;
}
