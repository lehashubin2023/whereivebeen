<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GameSessionJsonValidator
{
    /**
     * Define the validation rules.
     */
    protected static function rules(): array
    {
        return [
           //
        ];
    }

    /**
     * Define custom error messages (optional).
     */
    protected static function messages(): array
    {
        return [
            //
        ];
    }

    /**
     * Validate the array and return the validated data, or throw an exception.
     *
     * @throws ValidationException
     */
    public static function validate(array $data): array
    {
        return Validator::make($data, static::rules(), static::messages())->validate();
    }

    /**
     * Validate the array and return a boolean status alongside errors.
     */
    public static function check(array $data): array
    {
        $validator = Validator::make($data, static::rules(), static::messages());

        return [
            'passes' => $validator->passes(),
            'errors' => $validator->errors()->toArray(),
            'data'   => $validator->validated(),
        ];
    }
}
