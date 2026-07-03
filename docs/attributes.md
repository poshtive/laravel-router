# Attributes

All attributes are available in the `Poshtive\Router\Attributes` namespace.

## `Route`

Defines middleware, explicit URI segments, HTTP methods, and parameter ordering.

Targets: class or method

```php
use Poshtive\Router\Attributes\Route;

#[Route(middleware: ['auth'], keepOrder: true)]
class UserController
{
    #[Route(uri: 'profile', method: 'GET')]
    public function showProfile() {}

    #[Route(method: ['POST', 'PUT'], middleware: ['log'])]
    public function updateSection(int $id, string $section) {}

    #[Route(keepOrder: true)]
    public function customOrder(string $section, int $id) {}
}
```

This registers:

- `GET /profile` with `auth` middleware.
- `POST /user/{id}/update-section/{section}` with `auth` and `log` middleware.
- `PUT /user/{id}/update-section/{section}` with `auth` and `log` middleware.
- `GET /user/custom-order/{section}/{id}` with `auth` middleware.

On classes, only `middleware` and `keepOrder` are effective. `uri` and `method` are method-level options.

## `LocalOnly`

Registers routes only when the application environment is local.

Targets: class or method

```php
use Poshtive\Router\Attributes\LocalOnly;

#[LocalOnly]
class UserController
{
    public function index() {}
}
```

This registers `GET /user` only in the local environment.

## `DoNotDiscover`

Skips route discovery for a controller.

Target: class

```php
use Poshtive\Router\Attributes\DoNotDiscover;

#[DoNotDiscover]
class UserController
{
    public function index() {}
}
```

No routes from `UserController` are registered.

Route metadata on a parent class can still be inherited by child classes:

```php
use Poshtive\Router\Attributes\DoNotDiscover;
use Poshtive\Router\Attributes\Route;

#[DoNotDiscover]
#[Route(middleware: ['auth'])]
class AuthenticatedConcerns
{
}

class UserController extends AuthenticatedConcerns
{
    public function index() {}
}
```

This registers `GET /user` with `auth` middleware, while `AuthenticatedConcerns` itself is not discovered.

## `Where`

Adds regex constraints to route parameters.

Target: method

```php
use Poshtive\Router\Attributes\Where;

class UserController
{
    #[Where('id', '\d+')]
    public function show(int $id) {}
}
```

This registers `GET /user/{id}/show` with a numeric `{id}` constraint.

Multiple `Where` attributes can be applied to the same method:

```php
use Poshtive\Router\Attributes\Where;

class UserController
{
    #[Where('id', '\d+')]
    #[Where('slug', '[a-z0-9-]+')]
    public function show(int $id, string $slug) {}
}
```

All constraints must be satisfied.

## `IgnoreParentMiddleware`

Prevents inherited or class-level middleware from being applied.

Targets: class or method

When applied to a class, middleware defined in parent classes is ignored:

```php
use Poshtive\Router\Attributes\IgnoreParentMiddleware;

#[IgnoreParentMiddleware]
class UserController extends AuthenticatedConcerns
{
    public function index() {}
}
```

When applied to a method, class-level middleware is ignored for that method:

```php
use Poshtive\Router\Attributes\IgnoreParentMiddleware;
use Poshtive\Router\Attributes\Route;

#[Route(middleware: ['auth'])]
class AuthenticatedConcerns
{
}

class UserController extends AuthenticatedConcerns
{
    #[IgnoreParentMiddleware]
    public function show() {}

    public function app() {}
}
```

This registers `GET /user/show` without `auth` middleware. `GET /user/app` still receives `auth` middleware.
