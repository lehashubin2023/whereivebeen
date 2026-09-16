<?php

namespace App\Support\GameSession\SavedVariables;

use App\DTOs\GameSession\SavedVariablesSessionDTO;
use App\Exceptions\GameSession\SavedVariablesException;
use App\Support\Lua\LuaLexer;
use App\Support\Lua\LuaParseLimits;
use App\Support\Lua\LuaTableParser;
use App\Support\Lua\LuaTokenType;

class SavedVariablesReader
{
    public const GLOBAL_NAME = 'WhereIveBeenDB';

    public function __construct(private readonly SessionSpool $spool) {}

    /**
     * @param  callable(SavedVariablesSessionDTO): void  $onSession
     */
    public function read(
        string $absolutePath,
        string $batchUuid,
        LuaParseLimits $limits,
        callable $onSession,
    ): int {
        $stream = @fopen($absolutePath, 'rb');

        if ($stream === false) {
            throw SavedVariablesException::unreadable();
        }

        try {
            return $this->readStream($stream, $batchUuid, $limits, $onSession);
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     * @param  callable(SavedVariablesSessionDTO): void  $onSession
     */
    private function readStream($stream, string $batchUuid, LuaParseLimits $limits, callable $onSession): int
    {
        $lexer = new LuaLexer($stream, $limits);
        $parser = new LuaTableParser($lexer, $limits);

        $global = $lexer->next();

        if (! $global->is(LuaTokenType::NAME) || $global->value !== self::GLOBAL_NAME) {
            throw SavedVariablesException::globalNotFound(
                $global->is(LuaTokenType::NAME) ? (string) $global->value : null
            );
        }

        $parser->expect(LuaTokenType::ASSIGN);
        $parser->expect(LuaTokenType::LBRACE);

        $found = 0;

        $parser->streamTable(function (string|int $key, LuaTableParser $parser) use (
            &$found,
            $batchUuid,
            $limits,
            $onSession
        ) {
            if ($key !== 'sessions') {
                $parser->skipValue(1);

                return;
            }

            $parser->expect(LuaTokenType::LBRACE);

            $parser->streamTable(function (string|int $sessionKey, LuaTableParser $parser) use (
                &$found,
                $batchUuid,
                $limits,
                $onSession
            ) {
                $found++;

                if ($found > $limits->maxSessions) {
                    throw SavedVariablesException::tooManySessions($limits->maxSessions);
                }

                $onSession($this->readSession($sessionKey, $parser, $batchUuid, $limits));
            }, 2);
        }, 1);

        if ($found === 0) {
            throw SavedVariablesException::noSessions();
        }

        return $found;
    }

    private function readSession(
        string|int $sessionKey,
        LuaTableParser $parser,
        string $batchUuid,
        LuaParseLimits $limits,
    ): SavedVariablesSessionDTO {
        $parser->expect(LuaTokenType::LBRACE);

        $header = [];
        $pointsCount = 0;
        $spoolPath = '';

        $parser->streamTable(function (string|int $key, LuaTableParser $parser) use (
            &$header,
            &$pointsCount,
            &$spoolPath,
            $sessionKey,
            $batchUuid,
            $limits
        ) {
            if ($key !== 'points') {
                $header[(string) $key] = $parser->parseValue(4);

                return;
            }

            $parser->expect(LuaTokenType::LBRACE);

            $spool = $this->spool->open($batchUuid, $sessionKey);

            $parser->streamTable(function (string|int $index, LuaTableParser $parser) use (
                &$pointsCount,
                $spool,
                $sessionKey,
                $limits
            ) {
                $point = $parser->parseValue(5);

                if (! is_array($point)) {
                    return;
                }

                $spool->write($point);
                $pointsCount++;

                if ($pointsCount > $limits->maxPointsPerSession) {
                    throw SavedVariablesException::tooManyPoints($limits->maxPointsPerSession, $sessionKey);
                }
            }, 4);

            $spoolPath = $spool->close();
        }, 3);

        return new SavedVariablesSessionDTO(
            sessionId: $this->resolveSessionId($sessionKey, $header),
            header: $header,
            spoolPath: $spoolPath,
            pointsCount: $pointsCount,
        );
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function resolveSessionId(string|int $sessionKey, array $header): int
    {
        if (is_int($sessionKey) && $sessionKey > 0) {
            return $sessionKey;
        }

        if (is_string($sessionKey) && ctype_digit($sessionKey) && (int) $sessionKey > 0) {
            return (int) $sessionKey;
        }

        $id = $header['id'] ?? null;

        return is_numeric($id) ? (int) $id : 0;
    }
}
