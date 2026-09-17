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
            'file.required' => __('Pick the WhereIveBeen.lua file from your SavedVariables folder first.'),
            'file.max' => __('The file is larger than :size MB.', [
                'size' => (int) (LuaParseLimits::DEFAULT_MAX_BYTES / 1024 / 1024),
            ]),
            'file.extensions' => __('Pick the WhereIveBeen.lua file from your SavedVariables folder.'),
        ];
    }
}
