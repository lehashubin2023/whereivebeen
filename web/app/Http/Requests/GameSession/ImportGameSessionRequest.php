<?php

namespace App\Http\Requests\GameSession;

use App\Models\GameSession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportGameSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'game_session' => ['required', 'string', 'max:'.GameSession::MAX_IMPORT_SIZE],
        ];
    }
}
