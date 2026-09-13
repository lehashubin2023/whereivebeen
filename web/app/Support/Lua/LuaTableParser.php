<?php

namespace App\Support\Lua;

use App\Exceptions\Lua\LuaLimitExceededException;
use App\Exceptions\Lua\LuaSyntaxException;

/**
 * Рекурсивный спуск по таблицам Lua. `streamTable()` отдаёт пары наружу и
 * ничего не накапливает — на нём держится постоянный расход памяти при
 * разборе файлов в десятки мегабайт.
 */
class LuaTableParser
{
    public function __construct(
        private readonly LuaLexer $lexer,
        private readonly LuaParseLimits $limits,
    ) {}

    public function lexer(): LuaLexer
    {
        return $this->lexer;
    }

    /**
     * Разбирает значение целиком. Годится только для заведомо мелких
     * поддеревьев — таблица точек читается через streamTable().
     *
     * @return array<string|int, mixed>|string|int|float|bool|null
     */
    public function parseValue(int $depth = 0): array|string|int|float|bool|null
    {
        if ($depth > $this->limits->maxDepth) {
            throw LuaLimitExceededException::depth($this->limits->maxDepth, $this->lexer->line());
        }

        $token = $this->lexer->next();

        return match ($token->type) {
            LuaTokenType::STRING, LuaTokenType::NUMBER => $token->value,
            LuaTokenType::TRUE => true,
            LuaTokenType::FALSE => false,
            LuaTokenType::NIL => null,
            LuaTokenType::LBRACE => $this->collectTable($depth),
            default => throw LuaSyntaxException::at($token->line, $this->describe($token)),
        };
    }

    /**
     * Пропускает значение, ничего не возвращая.
     */
    public function skipValue(int $depth = 0): void
    {
        if ($depth > $this->limits->maxDepth) {
            throw LuaLimitExceededException::depth($this->limits->maxDepth, $this->lexer->line());
        }

        $token = $this->lexer->next();

        if ($token->is(LuaTokenType::LBRACE)) {
            $this->streamTable(fn (string|int $key, self $parser) => $parser->skipValue($depth + 1), $depth);

            return;
        }

        if (! in_array($token->type, [
            LuaTokenType::STRING,
            LuaTokenType::NUMBER,
            LuaTokenType::TRUE,
            LuaTokenType::FALSE,
            LuaTokenType::NIL,
        ], true)) {
            throw LuaSyntaxException::at($token->line, $this->describe($token));
        }
    }

    /**
     * Разбирает тело таблицы; курсор должен стоять сразу после `{`.
     * Останавливается, потребив `}`. Колбэк обязан потребить значение —
     * через parseValue(), skipValue() или вложенный streamTable().
     *
     * @param  callable(string|int, self): void  $onEntry
     */
    public function streamTable(callable $onEntry, int $depth = 0): int
    {
        if ($depth > $this->limits->maxDepth) {
            throw LuaLimitExceededException::depth($this->limits->maxDepth, $this->lexer->line());
        }

        $index = 1;
        $count = 0;

        while (true) {
            $token = $this->lexer->peek();

            if ($token->is(LuaTokenType::RBRACE)) {
                $this->lexer->next();

                return $count;
            }

            if ($token->is(LuaTokenType::EOF)) {
                throw LuaSyntaxException::unterminated($token->line, 'table');
            }

            $key = $this->readKey($index);

            $onEntry($key, $this);

            $count++;

            if ($count > $this->limits->maxTableEntries) {
                throw LuaLimitExceededException::entries($this->limits->maxTableEntries, $this->lexer->line());
            }

            $separator = $this->lexer->peek();

            if ($separator->is(LuaTokenType::COMMA) || $separator->is(LuaTokenType::SEMICOLON)) {
                $this->lexer->next();

                continue;
            }

            if ($separator->is(LuaTokenType::RBRACE)) {
                $this->lexer->next();

                return $count;
            }

            throw LuaSyntaxException::at($separator->line, $this->describe($separator));
        }
    }

    public function expect(LuaTokenType $type): LuaToken
    {
        $token = $this->lexer->next();

        if (! $token->is($type)) {
            throw LuaSyntaxException::at($token->line, $this->describe($token));
        }

        return $token;
    }

    /**
     * Ключ записи: `["name"]`, `[123]`, `name` или ничего — тогда это элемент
     * массивной части и ключом становится порядковый номер.
     */
    private function readKey(int &$index): string|int
    {
        $token = $this->lexer->peek();

        if ($token->is(LuaTokenType::LBRACKET)) {
            $this->lexer->next();
            $key = $this->lexer->next();

            if (! $key->is(LuaTokenType::STRING) && ! $key->is(LuaTokenType::NUMBER)) {
                throw LuaSyntaxException::at($key->line, $this->describe($key));
            }

            $this->expect(LuaTokenType::RBRACKET);
            $this->expect(LuaTokenType::ASSIGN);

            return is_int($key->value) ? $key->value : (string) $key->value;
        }

        if ($token->is(LuaTokenType::NAME) && $this->lexer->peek(1)->is(LuaTokenType::ASSIGN)) {
            $this->lexer->next();
            $this->lexer->next();

            return (string) $token->value;
        }

        return $index++;
    }

    /**
     * @return array<string|int, mixed>
     */
    private function collectTable(int $depth): array
    {
        $result = [];

        $this->streamTable(function (string|int $key, self $parser) use (&$result, $depth) {
            $result[$key] = $parser->parseValue($depth + 1);
        }, $depth + 1);

        return $result;
    }

    private function describe(LuaToken $token): string
    {
        return match ($token->type) {
            LuaTokenType::EOF => 'end of file',
            LuaTokenType::STRING, LuaTokenType::NUMBER, LuaTokenType::NAME => (string) $token->value,
            LuaTokenType::LBRACE => '{',
            LuaTokenType::RBRACE => '}',
            LuaTokenType::LBRACKET => '[',
            LuaTokenType::RBRACKET => ']',
            LuaTokenType::ASSIGN => '=',
            LuaTokenType::COMMA => ',',
            LuaTokenType::SEMICOLON => ';',
            LuaTokenType::TRUE => 'true',
            LuaTokenType::FALSE => 'false',
            LuaTokenType::NIL => 'nil',
        };
    }
}
