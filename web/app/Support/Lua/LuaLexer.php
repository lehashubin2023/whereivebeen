<?php

namespace App\Support\Lua;

use App\Exceptions\Lua\LuaLimitExceededException;
use App\Exceptions\Lua\LuaSyntaxException;

class LuaLexer
{
    private const WINDOW = 65536;

    private const ESCAPES = [
        'a' => "\x07",
        'b' => "\x08",
        'f' => "\x0C",
        'n' => "\n",
        'r' => "\r",
        't' => "\t",
        'v' => "\x0B",
        '\\' => '\\',
        '"' => '"',
        "'" => "'",
        "\n" => "\n",
    ];

    /**
     * @var resource
     */
    private $stream;

    private string $buffer = '';

    private int $pos = 0;

    private int $line = 1;

    private int $consumed = 0;

    private bool $drained = false;

    /**
     * @var array<int, LuaToken>
     */
    private array $lookahead = [];

    /**
     * @param  resource  $stream
     */
    public function __construct($stream, private readonly LuaParseLimits $limits)
    {
        $this->stream = $stream;
        $this->fill(3);

        if (str_starts_with(substr($this->buffer, $this->pos, 3), "\xEF\xBB\xBF")) {
            $this->pos += 3;
        }
    }

    public function next(): LuaToken
    {
        if ($this->lookahead !== []) {
            return array_shift($this->lookahead);
        }

        $this->compact();

        return $this->read();
    }

    public function peek(int $offset = 0): LuaToken
    {
        while (count($this->lookahead) <= $offset) {
            $this->compact();
            $this->lookahead[] = $this->read();
        }

        return $this->lookahead[$offset];
    }

    public function line(): int
    {
        return $this->line;
    }

    public function bytesRead(): int
    {
        return $this->consumed + $this->pos;
    }

    private function read(): LuaToken
    {
        $this->skipTrivia();

        $char = $this->at();

        if ($char === null) {
            return new LuaToken(LuaTokenType::EOF, null, $this->line);
        }

        $line = $this->line;

        $simple = match ($char) {
            '{' => LuaTokenType::LBRACE,
            '}' => LuaTokenType::RBRACE,
            ']' => LuaTokenType::RBRACKET,
            ',' => LuaTokenType::COMMA,
            ';' => LuaTokenType::SEMICOLON,
            default => null,
        };

        if ($simple !== null) {
            $this->advance();

            return new LuaToken($simple, null, $line);
        }

        if ($char === '=') {
            $this->advance();

            if ($this->at() === '=') {
                throw LuaSyntaxException::at($line, '==');
            }

            return new LuaToken(LuaTokenType::ASSIGN, null, $line);
        }

        if ($char === '[') {
            $level = $this->longBracketLevel();

            if ($level !== null) {
                return new LuaToken(LuaTokenType::STRING, $this->readLongBracket($level), $line);
            }

            $this->advance();

            return new LuaToken(LuaTokenType::LBRACKET, null, $line);
        }

        if ($char === '"' || $char === "'") {
            return new LuaToken(LuaTokenType::STRING, $this->readQuoted($char), $line);
        }

        if ($char === '-' || ctype_digit($char) || ($char === '.' && ctype_digit((string) $this->at(1)))) {
            return new LuaToken(LuaTokenType::NUMBER, $this->readNumber(), $line);
        }

        if ($char === '_' || ctype_alpha($char)) {
            $name = $this->readName();

            return match ($name) {
                'true' => new LuaToken(LuaTokenType::TRUE, true, $line),
                'false' => new LuaToken(LuaTokenType::FALSE, false, $line),
                'nil' => new LuaToken(LuaTokenType::NIL, null, $line),
                default => new LuaToken(LuaTokenType::NAME, $name, $line),
            };
        }

        throw LuaSyntaxException::at($line, $char);
    }

    private function skipTrivia(): void
    {
        while (true) {
            $char = $this->at();

            if ($char === null) {
                return;
            }

            if ($char === ' ' || $char === "\t" || $char === "\r" || $char === "\n") {
                $this->advance();

                continue;
            }

            if ($char === '-' && $this->at(1) === '-') {
                $this->advance(2);
                $level = $this->longBracketLevel();

                if ($level !== null) {
                    $this->readLongBracket($level);

                    continue;
                }

                while (($char = $this->at()) !== null && $char !== "\n") {
                    $this->advance();
                }

                continue;
            }

            return;
        }
    }

    private function longBracketLevel(): ?int
    {
        if ($this->at() !== '[') {
            return null;
        }

        $level = 0;

        while ($this->at($level + 1) === '=') {
            $level++;
        }

        return $this->at($level + 1) === '[' ? $level : null;
    }

    private function readLongBracket(int $level): string
    {
        $startLine = $this->line;
        $this->advance($level + 2);

        if ($this->at() === "\n") {
            $this->advance();
        }

        $closing = ']'.str_repeat('=', $level).']';
        $value = '';

        while (true) {
            $char = $this->at();

            if ($char === null) {
                throw LuaSyntaxException::unterminated($startLine, 'long string');
            }

            if ($char === ']' && $this->lookingAt($closing)) {
                $this->advance(strlen($closing));

                return $this->guardString($value, $startLine);
            }

            $value .= $char;
            $this->advance();

            if (strlen($value) > $this->limits->maxStringLength) {
                throw LuaLimitExceededException::stringLength($this->limits->maxStringLength, $startLine);
            }
        }
    }

    private function readQuoted(string $quote): string
    {
        $startLine = $this->line;
        $this->advance();
        $value = '';

        while (true) {
            $char = $this->at();

            if ($char === null || $char === "\n") {
                throw LuaSyntaxException::unterminated($startLine, 'string');
            }

            if ($char === $quote) {
                $this->advance();

                return $this->guardString($value, $startLine);
            }

            if ($char === '\\') {
                $value .= $this->readEscape($startLine);
            } else {
                $value .= $char;
                $this->advance();
            }

            if (strlen($value) > $this->limits->maxStringLength) {
                throw LuaLimitExceededException::stringLength($this->limits->maxStringLength, $startLine);
            }
        }
    }

    private function readEscape(int $startLine): string
    {
        $this->advance();
        $char = $this->at();

        if ($char === null) {
            throw LuaSyntaxException::unterminated($startLine, 'string');
        }

        if (ctype_digit($char)) {
            $digits = '';

            while (strlen($digits) < 3 && ($peek = $this->at()) !== null && ctype_digit($peek)) {
                $digits .= $peek;
                $this->advance();
            }

            return chr(abs((int) $digits) % 256);
        }

        if (! array_key_exists($char, self::ESCAPES)) {
            throw LuaSyntaxException::at($this->line, '\\'.$char);
        }

        $this->advance();

        return self::ESCAPES[$char];
    }

    private function readNumber(): int|float
    {
        $line = $this->line;
        $raw = '';

        if ($this->at() === '-') {
            $raw .= '-';
            $this->advance();
        }

        if ($this->at() === '0' && ($this->at(1) === 'x' || $this->at(1) === 'X')) {
            $raw .= '0x';
            $this->advance(2);

            while (($char = $this->at()) !== null && ctype_xdigit($char)) {
                $raw .= $char;
                $this->advance();
            }

            if (strlen($raw) <= (str_starts_with($raw, '-') ? 3 : 2)) {
                throw LuaSyntaxException::badNumber($line, $raw);
            }

            return (int) hexdec($raw);
        }

        $raw .= $this->readDigits();

        if ($this->at() === '.') {
            $raw .= '.';
            $this->advance();
            $raw .= $this->readDigits();
        }

        $exponent = $this->at();

        if ($exponent === 'e' || $exponent === 'E') {
            $raw .= 'e';
            $this->advance();

            if ($this->at() === '+' || $this->at() === '-') {
                $raw .= $this->at();
                $this->advance();
            }

            $digits = $this->readDigits();

            if ($digits === '') {
                throw LuaSyntaxException::badNumber($line, $raw);
            }

            $raw .= $digits;
        }

        if ($this->at() === '#' || ! is_numeric($raw)) {
            throw LuaSyntaxException::badNumber($line, $raw.(string) $this->at());
        }

        $number = $raw + 0;

        if (is_float($number) && ! is_finite($number)) {
            throw LuaSyntaxException::badNumber($line, $raw);
        }

        return $number;
    }

    private function readDigits(): string
    {
        $digits = '';

        while (($char = $this->at()) !== null && ctype_digit($char)) {
            $digits .= $char;
            $this->advance();
        }

        return $digits;
    }

    private function readName(): string
    {
        $name = '';

        while (($char = $this->at()) !== null && ($char === '_' || ctype_alnum($char))) {
            $name .= $char;
            $this->advance();
        }

        return $name;
    }

    private function guardString(string $value, int $line): string
    {
        if (strlen($value) > $this->limits->maxStringLength) {
            throw LuaLimitExceededException::stringLength($this->limits->maxStringLength, $line);
        }

        return mb_check_encoding($value, 'UTF-8')
            ? $value
            : mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    private function lookingAt(string $needle): bool
    {
        $this->fill(strlen($needle));

        return substr($this->buffer, $this->pos, strlen($needle)) === $needle;
    }

    private function at(int $offset = 0): ?string
    {
        $this->fill($offset + 1);
        $index = $this->pos + $offset;

        return $index < strlen($this->buffer) ? $this->buffer[$index] : null;
    }

    private function advance(int $count = 1): void
    {
        for ($step = 0; $step < $count; $step++) {
            $char = $this->at();

            if ($char === null) {
                return;
            }

            if ($char === "\n") {
                $this->line++;
            }

            $this->pos++;
        }
    }

    private function fill(int $needed): void
    {
        while (! $this->drained && strlen($this->buffer) - $this->pos < $needed) {
            $chunk = fread($this->stream, self::WINDOW);

            if ($chunk === false || $chunk === '') {
                $this->drained = true;

                break;
            }

            $this->buffer .= $chunk;

            if ($this->consumed + strlen($this->buffer) > $this->limits->maxBytes) {
                throw LuaLimitExceededException::bytes($this->limits->maxBytes);
            }
        }
    }

    private function compact(): void
    {
        if ($this->pos < self::WINDOW) {
            return;
        }

        $this->consumed += $this->pos;
        $this->buffer = substr($this->buffer, $this->pos);
        $this->pos = 0;
    }
}
