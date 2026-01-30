<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Routing Method Convention
    |--------------------------------------------------------------------------
    |
    | This setting defines the convention for discovering controller methods.
    |
    | 'prefix': Methods must be prefixed with the HTTP verb they respond to.
    |           e.g., `getIndex()`, `postStore()`, `deleteDestroy()`.
    |
    | 'attribute_or_get': Method names are used directly (e.g., `index()`).
    |                     The HTTP verb defaults to GET unless overridden by a
    |                     `#[Route(method: 'POST')]` attribute.
    |
    */
    'convention' => 'attribute_or_get',

    /*
    |--------------------------------------------------------------------------
    | Method Extendsion
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
    | HTTP Methods Map
    |--------------------------------------------------------------------------
    |
    | This setting defines the mapping of method names to HTTP verbs.
    | Only used when 'convention' is set to 'attribute_or_get'.
    | This is useful for RESTful controllers where method names like
    | `store`, `update`, and `destroy` correspond to specific HTTP verbs.
    |
    | You can customize the mapping as needed.
    |
    | The mapping should be in the format:
    | 'method_name' => 'HTTP_VERB'
    | or
    | 'method_name' => ['HTTP_VERB_1', 'HTTP_VERB_2']
    |
    | Method names from Route attributes will take precedence over this map.
    |
    */
    'http_methods_map' => []
];
