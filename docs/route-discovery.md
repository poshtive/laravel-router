# Route discovery

Files and directories determine the controller path. `User/ProfileController::index` becomes `/user/{user}/profile`; public methods are discovered by default. Constructors, destructors, static, magic, abstract, and explicitly excluded methods are ignored.

Class `#[Resource('profiles')]` replaces only the current controller segment. Method `#[Get('settings')]` replaces only the method segment, preserving parent segments. A `uri` starting with `/` provides an explicit full-URI escape hatch.

Generated names follow the same nested structure. Class `name` replaces the current controller name and method `name` is final; a group name prefix is applied outside both.

## HTTP methods and parameters

Verb attributes such as `#[Post]` take precedence over the resource conventions below; every other method is GET. A method name such as `postStore()` does not imply POST. Primitive parameters and model-typed parameters are used to fill convention placeholders. The first required binding is placed at the nearest conventional parent segment.

## Diagnostics

Before registration, the registrar checks HTTP methods, URI placeholders, duplicate method/URI signatures, and duplicate names. Non-strict mode logs warnings and continues. Strict mode throws `RouteDiscoveryException`, which makes invalid discovery fail during application boot or route-cache creation instead of silently reaching production.

`DoNotDiscover` and `LocalOnly` entries are retained as diagnostic definitions and can be reported with `report_skipped_routes`. This makes it possible to understand why a public method did not become a route without changing application behavior.

## Resource actions

For `BukuController`:

| Method | Verb | URI | Name |
| --- | --- | --- | --- |
| `index()` | `GET` | `/buku` | `buku.index` |
| `create()` | `GET` | `/buku/create` | `buku.create` |
| `store()` | `POST` | `/buku` | `buku.store` |
| `show(Buku $buku)` | `GET` | `/buku/{buku}` | `buku.show` |
| `edit(Buku $buku)` | `GET` | `/buku/{buku}/edit` | `buku.edit` |
| `update(Buku $buku)` | `PUT`, `PATCH` | `/buku/{buku}` | `buku.update` |
| `destroy(Buku $buku)` | `DELETE` | `/buku/{buku}` | `buku.destroy` |
| `terbitkan(Buku $buku)` | `GET` | `/buku/{buku}/terbitkan` | `buku.terbitkan` |

`index`, `store`, `show`, `update`, and `destroy` add no URI segment. Static
segments such as `/create` are registered before member placeholders.

Singletons need no attribute: a placeholder appears only when the method has a
parameter for it. `ProfileController::show()` is `GET /profile`, and
`Device/PairingController::store(Device $device)` is
`POST /device/{device}/pairing`.

URI segments are not pluralized: the class name is used as written.

## Index and parameter rules

`IndexController` and a method named `index` omit their respective URI segments. Primitive `int`/`string` parameters and Eloquent model parameters become placeholders in declaration order. Laravel performs model binding after registration.

By default the first required binding is placed before the method segment:

```text
UserController::archive(int $id, string $section)
GET /user/{id}/archive/{section}
```

Write an explicit URI such as `#[Get('archive/{id}/{section}')]` when parameters must follow the method segment.

Nullable and optional typed parameters are emitted as optional placeholders, such as `/user/{id?}`. Explicit placeholders preserve custom keys, for example `{user:slug}`. Use `#[ScopeBindings]` when nested model bindings must be scoped to their parent.

Each URI segment may contain at most one placeholder. Use separate segments such as `/reports/{year}/{month}`; composite segments such as `/reports/{year}-{month}` or `/items/{item}{detail}` are rejected as invalid in v2 because the discovery binding resolver does not yet support multiple placeholders in one segment. Strict mode throws `RouteDiscoveryException`; non-strict mode logs the diagnostic and skips the route.

## File mapping and ordering

Files are discovered in deterministic filename order. A configured `namespace` is joined to the path relative to the group directory, which supports module controllers. `patterns` and `not_patterns` run before reflection. Routes are sorted by specificity, URI, name, class, method, and discovery order before registration.

The manager runs once during provider boot and does nothing when Laravel's route cache is loaded. `router:diagnose` reports configured groups, registered route totals, skipped-route decisions, invalid definitions, duplicate signatures, and controllers that could not be loaded. Use `php artisan route:clear` followed by `php artisan route:cache` after deploying discovery changes.

## Nested controllers

Given `UserController.php` and `User/ProfileController.php`, `ProfileController::show(User $user, Profile $profile)` becomes `/user/{user}/profile/{profile}`. `IndexController` and an `index` method omit their respective segments. A folder named `Index` is rejected because it is ambiguous.

## Route names

Names mirror nested paths in kebab-case. Class `name` replaces the current controller segment, method `name` is final, and the group name prefix is applied last. Thus `api.v1.` plus `users.settings` becomes `api.v1.users.settings`.
