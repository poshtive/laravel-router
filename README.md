# Laravel Router

Convention-based route discovery for Laravel controllers using PHP attributes.

## Status

[![Tests](https://github.com/poshtive/laravel-router/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/poshtive/laravel-router/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/poshtive/laravel-router/branch/master/graph/badge.svg)](https://codecov.io/gh/poshtive/laravel-router)

`laravel-router` is stable.

- PHP: `^8.3`
- Laravel components: `^13.0`
- Test coverage: PHPUnit 11 + Orchestra Testbench 11, with `src/` at 100% locally

## Highlights

- Attribute-driven route discovery with minimal registration boilerplate
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

Register route discovery from your `routes/web.php` file:

```php
use Poshtive\Router\Router;

Router::create()->discover(app_path('Http/Controllers'));
```

Then add a controller method:

```php
namespace App\Http\Controllers;

class UserController
{
    public function index() {}
}
```

This registers `GET /user`.

You can still define Laravel routes manually as usual.

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
