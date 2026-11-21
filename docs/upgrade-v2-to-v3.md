# Upgrade guide: v2 to v3

v3 narrows the package to one job: turn controller folders and method
signatures into ordinary Laravel routes, following Laravel's resource
conventions. Most changes remove options; a few change generated URLs.

Plan for the URL changes first. They are the only ones your users can notice.

## Before you start

Record the current routes on v2 so you can compare them afterwards:

```bash
php artisan route:list --json > routes-v2.json
```

Then upgrade:

```bash
composer require poshtive/router:^3.0
```

## 1. Resource actions no longer add a method segment

Resource actions now produce the same verbs and URIs as `Route::resource()`.

| Method | v2 (`attribute_or_get`) | v3 |
| --- | --- | --- |
| `index()` | `GET /user` | `GET /user` |
| `create()` | `GET /user/create` | `GET /user/create` |
| `store()` | `GET /user/store` | `POST /user` |
| `show(User $user)` | `GET /user/{user}/show` | `GET /user/{user}` |
| `edit(User $user)` | `GET /user/{user}/edit` | `GET /user/{user}/edit` |
| `update(User $user)` | `GET /user/{user}/update` | `PUT\|PATCH /user/{user}` |
| `destroy(User $user)` | `GET /user/{user}/destroy` | `DELETE /user/{user}` |
| `publish(User $user)` | `GET /user/{user}/publish` | `GET /user/{user}/publish` |

The v2 verbs above assume no `#[Route(method: ...)]` or `http_methods_map`
entry; if you had those, your v2 verbs probably already matched v3. Route names
do not change.

To keep a v2 URL for a while, pin it with a verb attribute:

```php
#[Get('show')]                    // GET /user/{user}/show
public function show(User $user) {}

#[Post('store')]                  // POST /user/store
public function store() {}
```

Controller files without the `Controller` suffix no longer keep `.php` in their
URI and route name: `Helpers.php` gives `/helpers`, not `/helpers.php`.

## 2. Replace `#[Route]`

`#[Route]` is removed. Search for `#[Route(` and replace each use:

| v2 | v3 |
| --- | --- |
| `#[Route(method: 'POST')]` | `#[Post]` |
| `#[Route(method: ['PUT', 'PATCH'])]` | `#[Put]` and `#[Patch]` on separate lines |
| `#[Route(uri: 'settings', name: 'x')]` on a method | `#[Get('settings', name: 'x')]` (or the verb you need) |
| `#[Route(uri: 'profiles', name: 'profiles')]` on a class | `#[Resource('profiles', name: 'profiles')]` |
| `#[Route(middleware: ['auth', 'verified'])]` | `#[Middleware('auth', 'verified')]` |
| `#[Route(uri: '/teams/{team}', absolute: true)]` | `#[Get('/teams/{team}')]` (a leading `/` means absolute) |
| `#[Route(keepOrder: true)]` | An explicit `uri`, for example `#[Get('update/{id}/{section}')]` |
| `#[Route(scopeBindings: true)]` | `#[ScopeBindings]` |
| `#[Route(withoutScopedBindings: true)]` | `#[WithoutScopedBindings]` |

All attributes are in the `Poshtive\Router\Attributes` namespace. A class-level
absolute URI now keeps the method segment for actions other than `index`.

## 3. Update `config/router.php`

Remove these keys:

| Removed | Replacement |
| --- | --- |
| `convention` | Resource conventions. Rename `prefix`-style methods (`getIndex`, `postStore`) or add verb attributes. |
| `http_methods_map` | Verb attributes such as `#[Post]` |
| `method_extends` | `#[Discoverable]` on the parent class or trait (step 4) |
| `strict_naming` | None; generated names are never empty |
| `report_skipped_routes` | `php artisan router:check` |

Then check these:

- `strict` now defaults to `null`: strict in `local` and `testing`, lenient
  elsewhere. **Invalid or duplicate routes now throw during local boot.** Set
  `'strict' => false` while you migrate if you need to.
- Paths outside `app/` must set `namespace`. v2 guessed one from the folder
  name; v3 skips the path with a `discovery_namespace_missing` warning.
- New optional group option: `'pluralize' => true` (off by default).

## 4. Base controllers and traits

v3 only discovers methods written in the controller itself, or in a parent
class or trait marked `#[Discoverable]`. If you used `method_extends: true`:

```php
use Poshtive\Router\Attributes\Discoverable;

#[Discoverable]
abstract class CrudController extends Controller
{
    public function index() {}
    public function show(string $id) {}
}
```

Use `#[Only(...)]` or `#[Except(...)]` on a child to drop inherited actions.

Public methods from traits used directly in a controller were always routes in
v2. In v3 they are routes only when the trait is marked `#[Discoverable]`.

Several attributes of one kind on a parent class, such as two `#[Middleware]`,
now apply in the order they are written. v2 applied them in reverse, so check
the middleware order of routes whose parent class has more than one.

## 5. Commands

| v2 | v3 |
| --- | --- |
| `router:list` | `route:list` (supports `--path`, `--name`, `--json`) |
| `router:diagnose` | `router:check` |
| `router:diagnose --fail-on-warning` | `router:check` (always exits non-zero on warnings or errors) |

`router:check` runs discovery from scratch, so it works even when routes are
cached. Add it to CI.

## 6. Code that uses the PHP API

Only relevant if your code uses the package's PHP API directly.

- `Diagnostic` is now `new Diagnostic($code, Severity $severity, $message, $group, $path, $action)`;
  `severity` is the `Poshtive\Router\Discovery\Severity` enum.
- `RouteDiscoveryManager::diagnostics()` returns only `Diagnostic` objects.
- `RouteDiscoveryException::diagnostics()` returns the errors that caused it.
- `RouteDiscoveryManager::discover()` discovers on every call unless routes are
  cached; v2 ran it once.
- The `DiscoveryAttribute` marker interface is removed, and discovered routes'
  `_laravel_router` action metadata no longer has an `id`.

## Checklist

1. Remove the config keys listed in step 3; add `namespace` to paths outside `app/`.
2. Replace every `#[Route(...)]` (step 2).
3. Mark shared base controllers and action traits `#[Discoverable]` (step 4).
4. Run `php artisan router:check` and fix every warning and error.
5. Run `php artisan route:list --json > routes-v3.json` and compare it with
   `routes-v2.json`. Pin any URL that must not change yet (step 1).
6. Replace `router:list` and `router:diagnose` in scripts and CI (step 5).
7. Deploy with `php artisan route:clear && php artisan route:cache`.
