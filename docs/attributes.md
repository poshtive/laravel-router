# Attributes

Attributes are for exceptions to the conventions. Each attribute does one job.

| Attribute | Target | Purpose |
| --- | --- | --- |
| `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]` | method, repeatable | HTTP verb, with optional `uri` and `name` |
| `#[Middleware]` | class, method, repeatable | Append middleware |
| `#[IgnoreParentMiddleware]` | class, method | Drop inherited middleware |
| `#[Resource]` | class | Override the controller URI segment and name segment |
| `#[ScopeBindings]`, `#[WithoutScopedBindings]` | class, method | Laravel scoped model bindings |
| `#[Where]` | class, method, repeatable | Parameter constraint |
| `#[DoNotDiscover]` | class, method | Exclude from discovery |
| `#[LocalOnly]` | class, method | Register only in the local environment |

All attributes live in the `Poshtive\Router\Attributes` namespace.

## HTTP verbs

```php
use Poshtive\Router\Attributes\Patch;
use Poshtive\Router\Attributes\Post;
use Poshtive\Router\Attributes\Put;

#[Post]
public function store() {}

#[Put]
#[Patch]
public function update(User $user) {}

#[Post('publish', name: 'posts.publish')]
public function publishNow(Post $post) {}
```

- Several verb attributes on one method register one route that answers every
  declared verb.
- `uri` replaces only the method segment and keeps the folder and controller
  segments. A `uri` starting with `/` ignores those segments; the group prefix
  still applies.
- `name` is the final route name before the group's `name` prefix is added.
- When several verb attributes declare different `uri` or `name` values, the
  route is invalid.
- Verb attributes take precedence over `http_methods_map`; the fallback is GET.

URI overrides support one placeholder per segment. `reports/{year}/{month}` is
valid; `reports/{year}-{month}` is rejected. Explicit placeholders may use a
Laravel custom key, for example `#[Get('users/{user:slug}')]`.

## Middleware

```php
use Poshtive\Router\Attributes\Middleware;

#[Middleware('auth')]
class PostController
{
    #[Middleware('can:update,post', 'throttle:10,1')]
    public function update(Post $post) {}
}
```

Middleware is collected in this order, keeping the first occurrence of each
value: group, parent classes (root first), controller class, method.

`#[IgnoreParentMiddleware]` on a class drops parent-class middleware. On a
method it drops both parent-class and controller-class middleware. Group
middleware always applies.

Authorization uses Laravel's `can` middleware: `#[Middleware('can:update,post')]`.

## Resource segment

```php
use Poshtive\Router\Attributes\Resource;

#[Resource('profiles', name: 'profiles')]
class ProfileController {}
```

For `User/ProfileController`, this produces `/user/{user}/profiles/...` and
names such as `user.profiles.index`. A `uri` starting with `/` replaces every
folder-derived segment:

```php
#[Resource('/teams/{team}/members/{member}/settings')]
class SettingsController
{
    public function index(Team $team, Member $member) {}
}
```

## Scoped bindings

`#[ScopeBindings]` scopes nested model bindings to their parent;
`#[WithoutScopedBindings]` disables scoping. A method attribute overrides the
class attribute. Using both on the same target makes the route invalid.

## Constraints

`#[Where]` maps a parameter to a regular expression. Built-in constants include
`Where::ALPHA`, `Where::NUMERIC`, `Where::ALPHANUMERIC`, and `Where::UUID`.
Parent, class, and method constraints are merged in that order; later
declarations win.

## Exclusion

`#[DoNotDiscover]` keeps public helpers from becoming routes. `#[LocalOnly]` is
evaluated at discovery time, so the route is absent outside the local
environment. Set `report_skipped_routes` to log these decisions.
