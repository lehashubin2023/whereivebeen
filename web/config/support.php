<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Support Channels
    |--------------------------------------------------------------------------
    |
    | Voluntary donation channels shown on the "/support" page. Every entry is
    | optional: a channel left empty is filtered out and its card is not
    | rendered, so the page can ship with a single configured channel.
    |
    */

    'boosty' => env('SUPPORT_BOOSTY_URL'),

    'telegram' => env('SUPPORT_TELEGRAM_URL'),

    'crypto' => [
        'USDT (TRC-20)' => env('SUPPORT_USDT_TRC20'),
        'TON' => env('SUPPORT_TON'),
    ],

];
