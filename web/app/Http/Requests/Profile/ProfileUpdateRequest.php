<?php

namespace App\Http\Requests\Profile;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules($this->user()->id);
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if ($user === null || ! $user->isMainAdmin()) {
                    return;
                }

                if (strcasecmp((string) $this->input('email'), $user->email) !== 0) {
                    $validator->errors()->add(
                        'email',
                        __('The main administrator email cannot be changed.'),
                    );
                }
            },
        ];
    }
}
