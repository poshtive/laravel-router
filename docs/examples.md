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
use Poshtive\Router\Attributes\Post;

class OrderController
{
    public function index() {}

    public function show(User $user, Order $order) {}

    #[Post(name: 'orders.create')]
    public function store(User $user) {}
}
```

The resulting routes are:

| Method | URI | Name |
| --- | --- | --- |
| `GET` | `/api/v1/order` | `api.v1.order.index` |
| `GET` | `/api/v1/order/{user}/{order}` | `api.v1.order.show` |
| `POST` | `/api/v1/order/{user}` | `api.v1.orders.create` |

The group adds the `/api/v1` prefix and middleware. The controller segment is
derived from its name and resource actions add no method segment, while typed
model parameters provide the binding placeholders. A method-level `name` is the
final route name before the group name prefix is added.

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
#[Resource('profiles', name: 'profiles')]
class ProfileController
{
    #[Get('settings', name: 'account.settings')]
    public function edit(User $user) {}
}
```

The class override changes only `profile`; the method override changes only the final action segment. A `uri` starting with `/` is the explicit exception for a complete URI.

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

class UserController
{
    public function index() {}
    public function create() {}
    public function store() {}
    public function show(User $user) {}
    public function edit(User $user) {}
    public function update(User $user) {}
    public function destroy(User $user) {}
}
```

No attributes are needed: these register the same verbs and URIs as
`Route::resource('user', UserController::class)`.

## Nested override and middleware

For `User/ProfileController`, `#[Resource('profiles')]` changes only the child segment while retaining `/user/{user}`. A method `#[Get('settings', name: 'account.settings')]` changes only the final segment and uses the explicit final name. Group, inherited, class, and method `#[Middleware]` values are merged in that order.

Use `patterns` and `not_patterns` to select files without moving them:

```php
'patterns' => ['*Controller.php'],
'not_patterns' => ['*InternalController.php'],
```
