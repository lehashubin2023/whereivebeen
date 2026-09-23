<?php

use Illuminate\Support\Str;

return [

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug((string) env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    'middleware' => ['web'],

    'waits' => [
        'redis:import' => 60,
        'redis:default' => 60,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 64,

    'defaults' => [
        'import-supervisor' => [
            'connection' => 'redis',
            'queue' => ['import', 'default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 4,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 512,
            'tries' => 1,
            'timeout' => 600,
            'nice' => 0,
        ],

        'stats-supervisor' => [
            'connection' => 'redis',
            'queue' => ['statistics'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 1,
            'timeout' => 600,
            'nice' => 0,
        ],

        /**
         * Разбор файла SavedVariables держится в своей очереди, иначе одна
         * большая загрузка перекрывала бы вставку отдельных сессий.
         */
        'import-file-supervisor' => [
            'connection' => 'redis',
            'queue' => ['import-file'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 1,
            'timeout' => 900,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'import-supervisor' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 5,
                'balanceCooldown' => 5,
            ],

            'import-file-supervisor' => [
                'maxProcesses' => 3,
            ],

            'stats-supervisor' => [
                'maxProcesses' => 3,
            ],
        ],

        'local' => [
            'import-supervisor' => [
                'maxProcesses' => 3,
            ],

            'import-file-supervisor' => [
                'maxProcesses' => 1,
            ],

            'stats-supervisor' => [
                'maxProcesses' => 1,
            ],
        ],
    ],

];
