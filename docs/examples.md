# Examples

## End-to-end API controller

Configure a group for controllers in `app/Http/Controllers/Api`:

```php
// config/router.php
'groups' => [
    'api' => [
        'paths' => [app_path('Http/Controllers/Api')],
        'prefix' => 'api/v1',
        'name' => 'api.v1.',
        'middleware' => ['api', 'auth:sanctum'],
    ],
],
```

Create `app/Http/Controllers/Api/OrderController.php`:

```php
namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\User;
use Poshtive\Router\Attributes\Route;

class OrderController
{
    public function index() {}

    public function show(User $user, Order $order) {}

    #[Route(method: 'POST', name: 'orders.create')]
    public function store(User $user) {}
}
```

The resulting routes are:

| Method | URI | Name |
| --- | --- | --- |
| `GET` | `/api/v1/order` | `api.v1.order.index` |
| `GET` | `/api/v1/order/{user}/show/{order}` | `api.v1.order.show` |
| `POST` | `/api/v1/order/{user}/store` | `api.v1.orders.create` |

The group adds the `/api/v1` prefix and middleware. The controller and method
segments are derived from their names, while typed model parameters provide
the binding placeholders. Parameters are placed around the method segment by
the default convention; use `#[Route(keepOrder: true)]` to keep them after the
method. A method-level `name` is the final route name before the group name
prefix is added.

## Web and API

```php
'groups' => [
    'web' => ['paths' => [app_path('Http/Controllers/Web')], 'middleware' => ['web']],
    'api' => ['paths' => [app_path('Http/Controllers/Api')], 'prefix' => 'api', 'name' => 'api.', 'middleware' => ['api']],
],
```

For `Web/AccountController::index`, the result is `GET /account` with the `web` middleware. For `Api/UserController::index`, the result is `GET /api/user` with name `api.user.index` and the `api` middleware.

## Nested override

With `UserController.php` and `User/ProfileController.php`, a child route includes its parent binding:

```text
GET /user/{user}/profile
```

```php
#[Route(uri: 'profiles', name: 'profiles')]
class ProfileController
{
    #[Route(uri: 'settings', name: 'account.settings')]
    public function edit(User $user) {}
}
```

The class override changes only `profile`; the method override changes only the final action segment. `absolute: true` is the explicit exception for a complete URI.

## File selection

```php
'patterns' => ['*Controller.php'],
'not_patterns' => ['*InternalController.php'],
```

## Module

```php
'billing' => [
    'paths' => [base_path('modules/Billing/Http/Controllers')],
    'namespace' => 'Modules\\Billing\\Http\\Controllers\\',
    'prefix' => 'billing', 'name' => 'billing.', 'middleware' => ['web', 'auth'],
],
```

## REST controller

```php
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
    #[Route(method: 'DELETE')]
    public function destroy(User $user) {}
}
```

With an `api` group these become GET index/show, POST store, PUT/PATCH update, and DELETE destroy routes under the group prefix.

## Nested override and middleware

For `User/ProfileController`, `#[Route(uri: 'profiles')]` changes only the child segment while retaining `/user/{user}`. A method `#[Route(uri: 'settings', name: 'account.settings')]` changes only the final segment and uses the explicit final name. Class, method, inherited, and group middleware are merged in that order.

Use `patterns` and `not_patterns` to select files without moving them:

```php
'patterns' => ['*Controller.php'],
'not_patterns' => ['*InternalController.php'],
```
