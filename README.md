# Laravel Router

Convention-based route discovery for Laravel controllers, with PHP attributes for overrides and metadata.

## Status

[![Tests](https://github.com/poshtive/laravel-router/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/poshtive/laravel-router/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/poshtive/laravel-router/branch/master/graph/badge.svg)](https://codecov.io/gh/poshtive/laravel-router)

`laravel-router` is stable.

- PHP: `^8.3`
- Laravel components: `^13.0`
- Test coverage: PHPUnit 11 + Orchestra Testbench 11, with `src/` at 100% locally

## Highlights

- Public-method route discovery with minimal registration boilerplate
- Support for nested controller resources and model-bound parameters
- Convention-based HTTP verb resolution with optional explicit overrides
- Middleware, `where` constraints, and inheritance-aware discovery
- Strict duplicate detection and optional skipped-route reporting

## Installation

Install the package with Composer:

```bash
composer require poshtive/router
```

Optionally publish the configuration file:

```bash
php artisan vendor:publish --provider="Poshtive\Router\RouterServiceProvider" --tag="config"
```

## Quick Start

Configure route discovery in `config/router.php`:

```php
'groups' => [
    'web' => [
        'paths' => [app_path('Http/Controllers')],
        'middleware' => ['web'],
    ],
],
```

Then add a controller method:

```php
namespace App\Http\Controllers;

class UserController
{
    public function index() {}
}
```

This registers `GET /user` automatically after the service provider boots.

You can still define Laravel routes manually as usual.

## A Complete Example

Keep web and API controllers in separate directories when they use different
middleware or URL prefixes. For example, configure an API group like this:

```php
// config/router.php
'groups' => [
    'api' => [
        'paths' => [app_path('Http/Controllers/Api')],
        'prefix' => 'api',
        'name' => 'api.',
        'middleware' => ['api'],
    ],
],
```

Then add `app/Http/Controllers/Api/UserController.php`:

```php
namespace App\Http\Controllers\Api;

use App\Models\User;
use Poshtive\Router\Attributes\Route;

class UserController
{
    public function index() {}

    public function show(User $user) {}

    #[Route(method: 'POST')]
    public function store() {}

    #[Route(method: ['PUT', 'PATCH'])]
    public function update(User $user) {}
}
```

With the default `attribute_or_get` convention, discovery registers:

| Method | URI | Name |
| --- | --- | --- |
| `GET` | `/api/user` | `api.user.index` |
| `GET` | `/api/user/{user}/show` | `api.user.show` |
| `POST` | `/api/user/store` | `api.user.store` |
| `PUT`, `PATCH` | `/api/user/{user}/update` | `api.user.update` |

The `User $user` type hint supplies the `{user}` binding placeholder. Laravel
resolves the model after the route is registered. Method names default to GET;
use `#[Route(method: ...)]` or `http_methods_map` for other verbs.

## Documentation

- [Getting Started](docs/getting-started.md)
- [Configuration](docs/configuration.md)
- [Route Discovery](docs/route-discovery.md)
- [Attributes](docs/attributes.md)
- [Examples](docs/examples.md)

## Testing

Run the package test suite with:

```bash
composer test
```

Generate a Clover coverage report for `src/` with:

```bash
composer test:coverage
```

## Changelog

Release notes are tracked in [CHANGELOG.md](CHANGELOG.md).

## Contributing

Contributions are welcome. Please feel free to submit issues or pull requests.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
