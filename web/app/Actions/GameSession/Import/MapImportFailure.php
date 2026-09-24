<?php

namespace App\Actions\GameSession\Import;

use App\Exceptions\HasErrorCode;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

class MapImportFailure
{
    private const UNKNOWN_MAP_CONSTRAINT = 'way_points_map_id_foreign';

    private const DUPLICATE_SESSION_CONSTRAINT = 'game_sessions_user_id_game_session_id_unique';

    /**
     * @return array{code: string, context: array<string, scalar|null>}
     */
    public function exec(Throwable $e): array
    {
        if ($e instanceof HasErrorCode) {
            return ['code' => $e->errorCode(), 'context' => $e->errorContext()];
        }

        if ($e instanceof ValidationException) {
            $fields = array_keys($e->errors());

            return [
                'code' => 'import.validation_failed',
                'context' => [
                    'fields' => implode(', ', array_slice($fields, 0, 5)),
                    'count' => count($fields),
                ],
            ];
        }

        if ($e instanceof QueryException) {
            return $this->fromQuery($e);
        }

        if ($e instanceof JsonException) {
            return ['code' => 'import.invalid_json', 'context' => []];
        }

        return ['code' => 'import.unexpected', 'context' => ['class' => class_basename($e)]];
    }

    /**
     * @return array{code: string, context: array<string, scalar|null>}
     */
    private function fromQuery(QueryException $e): array
    {
        $sqlState = (string) $e->getCode();
        $message = $e->getMessage();

        if ($sqlState === '23000' && str_contains($message, self::UNKNOWN_MAP_CONSTRAINT)) {
            return ['code' => 'import.unknown_map', 'context' => []];
        }

        if ($sqlState === '23000' && str_contains($message, self::DUPLICATE_SESSION_CONSTRAINT)) {
            return ['code' => 'import.duplicate_session', 'context' => []];
        }

        if ($sqlState === '22003') {
            return ['code' => 'import.value_out_of_range', 'context' => []];
        }

        return ['code' => 'import.database_error', 'context' => ['sqlstate' => $sqlState]];
    }
}
