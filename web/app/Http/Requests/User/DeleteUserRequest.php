<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class DeleteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->managedUser()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('confirmation')) {
                    return;
                }

                if (strcasecmp((string) $this->input('confirmation'), $this->managedUser()->email) !== 0) {
                    $validator->errors()->add(
                        'confirmation',
                        __('Type the email of the user to confirm the deletion.'),
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
