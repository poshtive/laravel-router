# Route discovery

Files and directories determine the controller path. `User/ProfileController::index` becomes `/user/{user}/profile`; public methods are discovered by default. Constructors, destructors, static, magic, abstract, and explicitly excluded methods are ignored.

Class `#[Route(uri: 'profiles')]` replaces only the current controller segment. Method `#[Route(uri: 'settings')]` replaces only the method segment, preserving parent segments. `absolute: true` provides an explicit full-URI escape hatch.

Generated names follow the same nested structure. Class `name` replaces the current controller name and method `name` is final; a group name prefix is applied outside both.

## HTTP methods and parameters

In `attribute_or_get` mode, method-level `Route(method: ...)` takes precedence over `http_methods_map`, followed by GET. A method name such as `postStore()` does not imply POST in this mode. In `prefix` mode, recognized prefixes resolve the HTTP verb and an unprefixed method is still registered with fallback GET. Primitive parameters and model-typed parameters are used to fill convention placeholders. `keepOrder: true` keeps parameters in their declaration order; otherwise bindings are placed at the nearest conventional parent segment.

## Diagnostics

Before registration, the registrar checks HTTP methods, URI placeholders, duplicate method/URI signatures, and duplicate names. Non-strict mode logs warnings and continues. Strict mode throws `RouteDiscoveryException`, which makes invalid discovery fail during application boot or route-cache creation instead of silently reaching production.

`DoNotDiscover` and `LocalOnly` entries are retained as diagnostic definitions and can be reported with `report_skipped_routes`. This makes it possible to understand why a public method did not become a route without changing application behavior.

## Index and parameter rules

`IndexController` and a method named `index` omit their respective URI segments. Primitive `int`/`string` parameters and Eloquent model parameters become placeholders in declaration order. Laravel performs model binding after registration.

By default the first required binding is placed before the method segment:

```text
UserController::update(int $id, string $section)
GET /user/{id}/update/{section}
```

`#[Route(keepOrder: true)]` changes this to `/user/update/{id}/{section}` and can be applied to a class.

Nullable and optional typed parameters are emitted as optional placeholders, such as `/user/show/{id?}`. Explicit placeholders preserve custom keys, for example `{user:slug}`. Use `#[Route(scopeBindings: true)]` when nested model bindings must be scoped to their parent.

## File mapping and ordering

Files are discovered in deterministic filename order. A configured `namespace` is joined to the path relative to the group directory, which supports module controllers. `patterns` and `not_patterns` run before reflection. Routes are sorted by specificity, URI, name, class, method, and discovery order before registration.

The manager runs once during provider boot and does nothing when Laravel's route cache is loaded. `router:diagnose` reports configured groups, registered route totals, skipped-route decisions, invalid definitions, duplicate signatures, and controllers that could not be loaded. Use `php artisan route:clear` followed by `php artisan route:cache` after deploying discovery changes.

## Nested controllers

Given `UserController.php` and `User/ProfileController.php`, `ProfileController::show(User $user)` becomes `/user/{user}/profile/show`. `IndexController` and an `index` method omit their respective segments. A folder named `Index` is rejected because it is ambiguous.

## Route names

Names mirror nested paths in kebab-case. Class `name` replaces the current controller segment, method `name` is final, and the group name prefix is applied last. Thus `api.v1.` plus `users.settings` becomes `api.v1.users.settings`.
