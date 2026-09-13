<?php

namespace App\Support\GameSession\ImportProgress;

use App\Enums\GameSession\ImportOutcomeEnum;
use Throwable;

interface ImportGameSessionProgressContract
{
    /**
     * Пометить импорт «в работе» и зафиксировать общее число точек к обработке.
     */
    public function process(int $total): void;

    /**
     * Записать текущее число обработанных точек (вызывается в цикле по чанкам).
     */
    public function track(int $done): void;

    /**
     * Успешное завершение: статус + время работы + ссылка на созданную сессию.
     */
    public function complete(int $gameSessionId): void;

    /**
     * Чем кончилось создание сессии: новая запись или перезапись существующей.
     */
    public function outcome(ImportOutcomeEnum $outcome): void;

    /**
     * Провал: статус + время работы + код и сообщение об ошибке.
     *
     * @param  array{code: string, context: array<string, mixed>}|null  $failure
     */
    public function fail(Throwable $e, ?array $failure = null): void;

    /**
     * Импорт прошёл, но с замечаниями — например, часть точек попала на карты,
     * которых нет на сайте.
     *
     * @param  array<string, mixed>  $warnings
     */
    public function warn(array $warnings): void;
}
