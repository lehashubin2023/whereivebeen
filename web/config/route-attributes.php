<?php

use Illuminate\Routing\Middleware\SubstituteBindings;

return [
    'enabled' => true,

    'directories' => [
        app_path('Http/Controllers'),
    ],

    'middleware' => [
        'web',
        SubstituteBindings::class,
    ],

    'scope-bindings' => null,
];
