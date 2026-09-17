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
        return $this->user()?->can('update', $this->managedUser()) ?? false;
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
                if ($this->managedUser()->isMainAdmin()) {
                    $this->validateMainAdmin($validator);

                    return;
                }

                $this->validateAccountType($validator);
            },
        ];
    }

    public function managedUser(): User
    {
        /** @var User $user */
        $user = $this->route('managedUser');

        return $user;
    }

    private function validateMainAdmin(Validator $validator): void
    {
        $target = $this->managedUser();

        if (strcasecmp((string) $this->input('email'), $target->email) !== 0) {
            $validator->errors()->add(
                'email',
                __('The main administrator email cannot be changed.'),
            );
        }

        if (! $this->boolean('is_admin')) {
            $validator->errors()->add(
                'is_admin',
                __('The main administrator account type cannot be changed.'),
            );
        }
    }

    private function validateAccountType(Validator $validator): void
    {
        $target = $this->managedUser();

        if ($this->boolean('is_admin') === $target->isAdmin()) {
            return;
        }

        if ($this->user()?->is($target) === true) {
            $validator->errors()->add(
                'is_admin',
                __('You cannot change your own account type.'),
            );

            return;
        }

        if ($this->user()?->can('manageAdmins', User::class) !== true) {
            $validator->errors()->add(
                'is_admin',
                __('Only the main administrator can manage administrator access.'),
            );
        }
    }
}
