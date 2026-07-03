# Getting Started

This guide covers installation and the smallest route discovery setup.

## Installation

Install the package with Composer:

```bash
composer require poshtive/router
```

Optionally publish the configuration file:

```bash
php artisan vendor:publish --provider="Poshtive\Router\RouterServiceProvider" --tag="config"
```

The published file is available at `config/router.php`.

## Register Routes

Add route discovery to `routes/web.php`:

```php
use Poshtive\Router\Router;

Router::create()->discover(app_path('Http/Controllers'));
```

Manual Laravel routes can still be defined alongside discovered routes.

## First Controller

Create a controller method:

```php
namespace App\Http\Controllers;

class UserController
{
    public function index() {}

    public function show(int $id) {}
}
```

With the default `attribute_or_get` convention, this registers:

- `GET /user`
- `GET /user/{id}/show`

## Explicit HTTP Methods

Use the `Route` attribute when a method should not default to `GET`:

```php
namespace App\Http\Controllers;

use Poshtive\Router\Attributes\Route;

class UserController
{
    #[Route(method: 'POST')]
    public function store() {}

    #[Route(method: ['PUT', 'PATCH'])]
    public function update(int $id) {}
}
```

This registers:

- `POST /user/store`
- `PUT /user/{id}/update`
- `PATCH /user/{id}/update`

## Next Steps

- Configure route naming and discovery behavior in [Configuration](configuration.md).
- Learn how controller names, methods, and parameters become routes in [Route Discovery](route-discovery.md).
- Review available PHP attributes in [Attributes](attributes.md).
