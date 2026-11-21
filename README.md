# Laravel Router

Convention-based route discovery for Laravel controllers, with PHP attributes for overrides and metadata.

## Status

[![Tests](https://github.com/poshtive/laravel-router/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/poshtive/laravel-router/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/poshtive/laravel-router/branch/master/graph/badge.svg)](https://codecov.io/gh/poshtive/laravel-router)

`laravel-router` is stable.

- PHP: `^8.3`
- Laravel components: `^12.0|^13.0`
- Test coverage: PHPUnit 11 + Orchestra Testbench 10/11, with `src/` at 100% locally

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
use Poshtive\Router\Attributes\Post;

class UserController
{
    public function index() {}

    public function store() {}

    public function show(User $user) {}

    public function update(User $user) {}

    public function destroy(User $user) {}

    #[Post]
    public function ban(User $user) {}
}
```

Resource actions follow Laravel's resource conventions, so discovery registers:

| Method | URI | Name |
| --- | --- | --- |
| `GET` | `/api/user` | `api.user.index` |
| `POST` | `/api/user` | `api.user.store` |
| `GET` | `/api/user/{user}` | `api.user.show` |
| `PUT`, `PATCH` | `/api/user/{user}` | `api.user.update` |
| `DELETE` | `/api/user/{user}` | `api.user.destroy` |
| `POST` | `/api/user/{user}/ban` | `api.user.ban` |

The `User $user` type hint supplies the `{user}` binding placeholder. Laravel
resolves the model after the route is registered. Other public methods are GET
actions; use verb attributes such as `#[Post]` for other verbs.

## Documentation

- [Getting Started](docs/getting-started.md)
- [Configuration](docs/configuration.md)
- [Route Discovery](docs/route-discovery.md)
- [Attributes](docs/attributes.md)
- [Examples](docs/examples.md)
- [Upgrading from v2](docs/upgrade-v2-to-v3.md)

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
