# Examples

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
