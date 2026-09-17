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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'game_session.required' => __('Paste the session exported from the addon first.'),
            'game_session.max' => __('This session is too large to import.'),
        ];
    }
}
