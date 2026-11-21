<?php

return [
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Discovery Groups
    |--------------------------------------------------------------------------
    |
    | These defaults discover the conventional web and API controller roots.
    | Remove a group or set enabled=false when an application does not use it.
    |
    */
    'groups' => [
        'web' => [
            'paths' => [app_path('Http/Controllers/Web')],
            'middleware' => ['web'],
        ],

        'api' => [
            'paths' => [app_path('Http/Controllers/Api')],
            'prefix' => 'api',
            'name' => 'api.',
            'middleware' => ['api'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Strict Discovery
    |--------------------------------------------------------------------------
    |
    | When strict, any invalid or duplicate discovered route throws a
    | RouteDiscoveryException before a single route is registered. When not
    | strict, those routes are skipped and logged as warnings.
    |
    | null: strict in the local and testing environments, lenient elsewhere.
    |
    */
    'strict' => null,
];
