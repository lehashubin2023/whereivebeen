<?php

namespace App\Http\Requests\User;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

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
            'email' => $this->emailRules($this->managedUser()->id),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'is_admin' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->managedUser()->id !== $this->user()?->id) {
                    return;
                }

                if (! $this->boolean('is_admin')) {
                    $validator->errors()->add(
                        'is_admin',
                        __('You cannot remove your own administrator access.'),
                    );
                }
            },
        ];
    }

    public function managedUser(): User
    {
        /** @var User $user */
        $user = $this->route('managedUser');

        return $user;
    }
}
