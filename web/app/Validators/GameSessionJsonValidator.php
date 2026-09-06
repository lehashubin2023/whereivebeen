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

            'schema' => 'sometimes|integer',
            'ended' => 'sometimes|nullable|integer',
            'continuesFrom' => 'sometimes|nullable|integer',
            'addon' => 'sometimes|nullable|string|max:16',
            'gameVersion' => 'sometimes|nullable|string|max:16',
            'build' => 'sometimes|nullable|string|max:16',
            'locale' => 'sometimes|nullable|string|max:8',
            'faction' => 'sometimes|nullable|string|max:16',
            'class' => 'sometimes|nullable|string|max:16',
            'level' => 'sometimes|nullable|integer',

            'points' => 'required|array',
            'points.*.x' => 'required|numeric',
            'points.*.y' => 'required|numeric',
            'points.*.t' => 'sometimes|numeric',
            'points.*.mapId' => 'sometimes|nullable|numeric',
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

            'points.*.items' => 'sometimes|array',
            'points.*.items.*.id' => 'required_with:points.*.items|integer',
            'points.*.items.*.name' => 'sometimes|nullable|string',
            'points.*.items.*.n' => 'sometimes|numeric',

            'points.*.joined' => 'sometimes|array',
            'points.*.joined.*' => 'string',
            'points.*.left' => 'sometimes|array',
            'points.*.left.*' => 'string',

            'points.*.places' => 'sometimes|array',
            'points.*.places.*' => 'string',
            'points.*.npcId' => 'sometimes|nullable|integer',
            'points.*.npcName' => 'sometimes|nullable|string',

            'points.*.zone' => 'sometimes|string',
            'points.*.subZone' => 'sometimes|nullable|string',

            'points.*.node' => 'sometimes|array',
            'points.*.node.name' => 'sometimes|nullable|string',
            'points.*.node.objectId' => 'sometimes|nullable|integer',
            'points.*.node.prof' => 'sometimes|nullable|string',
            'points.*.node.spellId' => 'sometimes|nullable|integer',
            'points.*.node.spellName' => 'sometimes|nullable|string',

            'points.*.killer' => 'sometimes|array',
            'points.*.killer.name' => 'sometimes|nullable|string',
            'points.*.killer.npcId' => 'sometimes|nullable|integer',
            'points.*.killer.spell' => 'sometimes|nullable|string',
            'points.*.killer.amount' => 'sometimes|nullable|numeric',
            'points.*.killer.pvp' => 'sometimes|boolean',
            'points.*.environment' => 'sometimes|string',

            'points.*.reason' => 'sometimes|string',
            'points.*.spellId' => 'sometimes|nullable|integer',
            'points.*.spellName' => 'sometimes|nullable|string',
            'points.*.seconds' => 'sometimes|numeric',
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
