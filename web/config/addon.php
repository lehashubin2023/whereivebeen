<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Addon Package
    |--------------------------------------------------------------------------
    |
    | The addon sources live next to the web application. The packaging command
    | zips them into "public/{directory}/{name}-{version}.zip", keeping the
    | folder name the game client requires as the archive root.
    |
    */

    'name' => 'WhereIveBeen',

    'source' => env('ADDON_SOURCE_PATH', base_path('../addon')),

    'directory' => 'downloads',

];
