# Examples

These examples show common route discovery patterns.

## REST-like Controller

```php
namespace App\Http\Controllers;

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

This registers:

- `GET /user`
- `GET /user/{user}/show`
- `POST /user/store`
- `PUT /user/{user}/update`
- `PATCH /user/{user}/update`
- `DELETE /user/{user}/destroy`

## Prefix Convention

Set `convention` to `prefix` in `config/router.php`:

```php
'convention' => 'prefix',
```

Then name controller methods with verb prefixes:

```php
class UserController
{
    public function getIndex() {}

    public function postStore() {}

    public function putUpdate(int $id) {}

    public function deleteDestroy(int $id) {}
}
```

This registers:

- `GET /user`
- `POST /user/store`
- `PUT /user/{id}/update`
- `DELETE /user/{id}/destroy`

## Shared Middleware

Use a class-level `Route` attribute for middleware shared by every route in the controller:

```php
use Poshtive\Router\Attributes\Route;

#[Route(middleware: ['auth'])]
class AccountController
{
    public function index() {}

    #[Route(middleware: ['verified'])]
    public function billing() {}
}
```

This registers:

- `GET /account` with `auth` middleware.
- `GET /account/billing` with `auth` and `verified` middleware.

## Middleware Inheritance

Put shared route metadata on a parent class and prevent that parent from being discovered directly:

```php
use Poshtive\Router\Attributes\DoNotDiscover;
use Poshtive\Router\Attributes\Route;

#[DoNotDiscover]
#[Route(middleware: ['auth'])]
abstract class AuthenticatedController
{
}

class DashboardController extends AuthenticatedController
{
    public function index() {}
}
```

This registers `GET /dashboard` with `auth` middleware.

## Child Resource Controller

Given this structure:

```text
app/Http/Controllers/
├── UserController.php
└── User/
    └── ProfileController.php
```

And this child controller:

```php
namespace App\Http\Controllers\User;

use App\Models\User;

class ProfileController
{
    public function show(User $user) {}
}
```

This registers:

```text
GET /user/{user}/profile/show
```

## Local-only Routes

Use `LocalOnly` for routes that should exist only in local development:

```php
use Poshtive\Router\Attributes\LocalOnly;

class DebugController
{
    #[LocalOnly]
    public function routes() {}
}
```

This registers `GET /debug/routes` only when the application environment is local.

## Strict Duplicate Detection

Enable strict discovery once route structure is stable:

```php
'strict' => true,
```

When duplicate route names or duplicate `HTTP_VERB + URI` combinations are discovered, registration fails immediately.
