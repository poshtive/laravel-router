# Attributes

`#[Route]` supports `uri`, `method`, `name`, `middleware`, `keepOrder`, `absolute`, `scopeBindings`, and `withoutScopedBindings`. `#[DoNotDiscover]` works on classes and methods. `#[LocalOnly]`, `#[IgnoreParentMiddleware]`, and repeatable `#[Where]` remain available.

```php
#[Route(method: ['PUT', 'PATCH'], name: 'users.update', middleware: ['auth'])]
public function update(User $user) {}
```

Class URI/name values replace only the current controller segment. Method URI values replace the method segment while retaining parent segments. A method-level name is not combined with the generated name; it is the final name before the group's `name` prefix is applied. `absolute: true` is intended for a method-level URI that must bypass nested convention entirely.

Middleware is accumulated from inherited route attributes, the controller, the method, and finally the discovery group. `IgnoreParentMiddleware` removes inherited middleware while retaining method/class middleware. `Where` constraints use the parameter name as the key and later declarations override earlier declarations.

## Exclusion and constraints

`DoNotDiscover` may be placed on a class or a public method. `LocalOnly` supports the same targets and excludes routes outside the local environment. Set `report_skipped_routes` to explain these decisions in the logger.

`Where` is repeatable and maps a route parameter to a regular expression. Built-in constants include `ALPHA`, `NUMERIC`, `ALPHANUMERIC`, and `UUID`; parent, class, and method constraints are merged in that order.

Nullable or optional typed parameters produce optional placeholders such as `{id?}`. Explicit placeholders may preserve a Laravel custom route key, for example `{user:slug}`. Use `scopeBindings: true` to enable nested scoped model binding, or `withoutScopedBindings: true` to disable it for a route.

## Route examples

```php
use Poshtive\Router\Attributes\Route;

#[Route(middleware: ['auth'], keepOrder: true)]
class UserController
{
    public function index() {}

    #[Route(uri: 'profile', method: 'GET')]
    public function showProfile() {}

    #[Route(method: ['POST', 'PUT'], middleware: ['audit'])]
    public function update(int $id, string $section) {}
}
```

Class middleware applies to every route and method middleware is merged uniquely. `keepOrder` places parameters after the method segment; without it, the first binding is placed at the nearest parent position.

## Exclusion examples

`DoNotDiscover` is useful for public helpers that should not become routes. `LocalOnly` is evaluated at discovery time, so the route is absent outside local environments. Both attributes can target a class or a method.
