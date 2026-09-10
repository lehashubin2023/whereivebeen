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

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
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
     * Get the additional validation callbacks that should run after validation.
     *
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
