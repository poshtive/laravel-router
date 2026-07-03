# Configuration

After publishing the config file, customize route discovery in `config/router.php`.

## Sample Configuration

```php
return [
    'convention' => 'attribute_or_get',
    'method_extends' => false,
    'http_methods_map' => [
        'store' => 'POST',
        'update' => ['PUT', 'PATCH'],
        'destroy' => 'DELETE',
    ],
    'report_skipped_routes' => false,
    'strict' => false,
];
```

## `convention`

Controls how controller methods become routes.

Default: `attribute_or_get`

### `attribute_or_get`

Plain public method names become `GET` routes unless a `Route` attribute or `http_methods_map` entry overrides the method.

```php
use Poshtive\Router\Attributes\Route;

class UserController
{
    public function index() {}

    #[Route(method: 'POST')]
    public function store() {}

    #[Route(method: ['PUT', 'PATCH'])]
    public function updateApp() {}
}
```

This registers:

- `GET /user`
- `POST /user/store`
- `PUT /user/update-app`
- `PATCH /user/update-app`

### `prefix`

Method names must start with an HTTP verb. The remaining action name is converted to kebab-case.

Pattern: `{verb}{StudlyAction}`

```php
class UserController
{
    public function getIndex() {}

    public function postStore() {}

    public function deleteDestroyApp() {}
}
```

This registers:

- `GET /user`
- `POST /user/store`
- `DELETE /user/destroy-app`

Public methods that do not match the prefix pattern are skipped. Enable `report_skipped_routes` to log those skipped methods.

## `method_extends`

Controls whether inherited public methods are discovered.

Default: `false`

- `false`: only methods declared on the concrete controller are scanned.
- `true`: inherited public methods are also scanned.

```php
use Poshtive\Router\Attributes\DoNotDiscover;

#[DoNotDiscover]
abstract class BaseCrudController
{
    public function index() {}
}

class UserController extends BaseCrudController
{
    public function show() {}
}
```

When `method_extends` is `true`, both `index` and `show` register as `UserController` routes.

## `http_methods_map`

Maps method names to HTTP verbs when `convention` is `attribute_or_get` and no method-level `Route` attribute is present.

Default:

```php
[
    'store' => 'POST',
    'update' => ['PUT', 'PATCH'],
    'destroy' => 'DELETE',
]
```

Accepted values are a string or an array of strings:

```php
'http_methods_map' => [
    'store' => 'POST',
    'update' => ['PUT', 'PATCH'],
    'destroy' => 'DELETE',
],
```

Method-level `#[Route(method: ...)]` values take precedence over this map.

## `report_skipped_routes`

Logs intentionally skipped discovered methods.

Default: `false`

Skipped routes include controllers marked with `#[DoNotDiscover]`, routes guarded by `#[LocalOnly]` outside the local environment, and public methods that do not match the `prefix` convention.

## `strict`

Controls duplicate discovery behavior.

Default: `false`

- `false`: duplicate definitions are reported to the logger when available.
- `true`: duplicate route names or duplicate `HTTP_VERB + URI` combinations throw an exception and stop registration.

## Decision Guide

- Prefer `prefix` for explicit method names without attributes.
- Prefer `attribute_or_get` for clean method names with selective attributes.
- Enable `method_extends` when using abstract or base controllers for shared actions.
- Populate `http_methods_map` to reduce repetitive attributes for common REST verbs.
- Enable `report_skipped_routes` while integrating the package into an existing application.
- Enable `strict` once your route structure is stable and duplicate discovery should fail fast.
