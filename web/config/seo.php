<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SEO Defaults
    |--------------------------------------------------------------------------
    |
    | Copy for titles and descriptions lives in "lang/{locale}/seo.php". This
    | file holds only the values that do not need translating: the preview
    | image used by social cards and the routes that search engines may index.
    |
    */

    'og_image' => env('SEO_OG_IMAGE', '/maps/Hellfire_Peninsula.png'),

    'indexable' => [
        'home',
        'faq',
        'support',
    ],

];
