<?php

namespace App\Http\Requests\Profile;

use App\Enums\LocaleEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocaleUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', Rule::enum(LocaleEnum::class)],
        ];
    }

    public function locale(): LocaleEnum
    {
        return LocaleEnum::from($this->string('locale')->toString());
    }
}
