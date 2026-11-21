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
    | Method Extension
    |--------------------------------------------------------------------------
    |
    | This setting defines whether the method from extended class should be
    | registered as part of the routes for current controller.
    |
    | true:  Methods from parent class will be registered.
    | false: Only methods from the current class will be registered.
    |
    */
    'method_extends' => false,

    /*
    |--------------------------------------------------------------------------
    | Report Skipped Routes
    |--------------------------------------------------------------------------
    |
    | When enabled, discovered methods that are intentionally skipped by
    | discovery rules such as #[DoNotDiscover] or #[LocalOnly] will be logged.
    |
    */
    'report_skipped_routes' => false,

    /*
    |--------------------------------------------------------------------------
    | Strict Discovery
    |--------------------------------------------------------------------------
    |
    | When enabled, duplicate route names or duplicate HTTP verb + URI pairs
    | discovered during registration will throw an exception instead of only
    | being reported to the logger.
    |
    */
    'strict' => false,

    'strict_naming' => false,
];
