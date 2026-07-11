# Getting started

Install the package with `composer require poshtive/router`, publish the config, and configure discovery groups. No call is required from `routes/web.php` or `routes/api.php`.

```php
// config/router.php
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
```

Public instance methods on discovered controllers become routes. Manual Laravel routes continue to work beside discovered routes.

## Lifecycle and cache

Discovery runs once while the service provider boots. It is not invoked for every request. When Laravel reports that routes are cached, discovery is skipped so `route:cache` remains the single source of truth. After changing controllers or configuration in production, run:

```bash
php artisan route:clear
php artisan route:cache
```

Use `php artisan route:list` to inspect the resulting Laravel routes. The package does not create a second route cache.

The package also provides `php artisan router:list` for the same route set and `php artisan router:diagnose` for discovery status, configured group count, path count, and registered-route totals.

## Choosing a group

Keep web and API controllers in separate groups when their middleware or URL contracts differ. Use `namespace` for module directories outside the normal `App` tree. A group prefix is applied after controller discovery, so controller URI overrides cannot accidentally remove it.

## First controller

```php
namespace App\Http\Controllers\Web;

class UserController
{
    public function index() {}
    public function show(int $id) {}
}
```

With `attribute_or_get`, this produces `GET /user` and `GET /user/{id}/show`. Constructors, destructors, static methods, magic methods, and abstract controllers are excluded. Inherited methods are controlled by `method_extends`.

For a controller under the API path, the same `index()` method produces `GET /api/user` and receives the `api` middleware because the group is applied outside controller discovery.

Manual Laravel routes remain valid alongside discovered routes.

## Explicit methods

```php
use Poshtive\Router\Attributes\Route;

class UserController
{
    #[Route(method: 'POST')]
    public function store() {}

    #[Route(method: ['PUT', 'PATCH'])]
    public function update(int $id) {}
}
```

These methods become `POST /user/store`, `PUT /user/{id}/update`, and `PATCH /user/{id}/update`. Use `http_methods_map` when the same REST convention applies across many controllers.

See [Configuration](configuration.md), [Route Discovery](route-discovery.md), [Attributes](attributes.md), and [Examples](examples.md) for the complete behavior reference.
