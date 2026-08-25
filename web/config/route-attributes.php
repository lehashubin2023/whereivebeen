<?php

return [
    'enabled' => true,

    'directories' => [
        app_path('Http/Controllers'),
    ],

    'middleware' => [
        'web',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],

    'scope-bindings' => null,
];
