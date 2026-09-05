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
            'version' => 'required|integer',
            'sessionId' => 'required|integer',
            'started' => 'required|integer',
            'char' => 'required|string',
            'realm' => 'required|string',

            'points' => 'required|array',
            'points.*.x' => 'required|numeric',
            'points.*.y' => 'required|numeric',
            'points.*.t' => 'sometimes|numeric',
            'points.*.mapId' => 'required|numeric',
            'points.*.event' => 'sometimes|string',

            'points.*.questId' => 'sometimes|numeric',
            'points.*.level' => 'sometimes|numeric',
            'points.*.itemId' => 'sometimes|numeric',
            'points.*.itemName' => 'sometimes|string',
            'points.*.count' => 'sometimes|numeric',
            'points.*.action' => 'sometimes|string',
            'points.*.title' => 'sometimes|string',
            'points.*.place' => 'sometimes|string',
            'points.*.member' => 'sometimes|string',
            'points.*.inCombat' => 'sometimes|boolean',
            'points.*.mounted' => 'sometimes|boolean',
            'points.*.onTaxi' => 'sometimes|boolean',
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
}
