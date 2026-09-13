<?php

namespace App\Http\Requests\GameSession;

use App\Support\Lua\LuaParseLimits;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportSavedVariablesRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'max:'.(int) (LuaParseLimits::DEFAULT_MAX_BYTES / 1024),
                // Не `mimes`: finfo отдаёт для .lua то text/plain, то
                // application/octet-stream. Настоящая проверка — парсер.
                'extensions:lua,txt',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => __('Pick the WhereIveBeen.lua file from your SavedVariables folder.'),
        ];
    }
}
