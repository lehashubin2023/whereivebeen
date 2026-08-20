<?php

namespace App\Support\GameSession\ImportProgress;

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
     * Провал: статус + время работы + сообщение об ошибке.
     */
    public function fail(Throwable $e): void;
}
