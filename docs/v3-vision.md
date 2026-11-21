# v3 vision

This document records the direction for v3. Treat it as the scope filter:
a feature that does not serve the goal below does not belong in the package.

## Goal

> The folder structure is the URL, the method signature supplies the
> parameters, and Laravel conventions choose the verbs. The output is plain
> Laravel routes.

Laravel's resource routes work well, but they make you register every
controller by hand. This package removes that step and stays out of the way
everywhere else.

## Principles

1. **The output is only Laravel routes.** No parallel registry or manifest.
   `route:cache`, `route:list`, Wayfinder, and every other tool that reads the
   route collection work without extra integration.
2. **Resourceful by default.** Conventional action names map to Laravel's
   resource verbs and URIs. Other public methods are `GET` actions.
3. **What you write is what you get.** URL segments come from folder and class
   names exactly as written. The package does not guess, pluralize, or infer
   unless you opt in.
4. **Attributes are for exceptions.** Each attribute does one job. There is no
   catch-all attribute.
5. **Explicit over implicit.** Behavior lives in code next to the controller,
   not in global configuration switches.
6. **Fail loud.** Invalid or conflicting routes throw in local and testing
   environments by default.
7. **Cross-platform.** Linux, macOS, and Windows are supported and covered in CI.

## Non-goals

- Replacing Laravel's route registration, authorization, or middleware systems.
- Generating client code (Wayfinder already does this from Laravel routes).
- Inferring resource shape from controller contents.
- Language-aware pluralization by default.

## Conventions

### URIs and verbs

For `app/Http/Controllers/BukuController.php`:

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

- Placeholders come from parameter names and types (model, string-backed enum,
  or scalar), as in v2.
- Static segments such as `/create` register before member placeholders.
- Custom actions default to `GET`; use a verb attribute to change it.

### Nesting

Nested folders express parent resources, as in v2. `Buku/BabController.php`
next to `BukuController.php` produces `/buku/{buku}/bab/...`. The first bound
parameter belongs to the parent.

### Singular segments and pluralization

Segments are not pluralized by default, so `BukuController` stays `/buku` and
`UserController` stays `/user`.

- A group can opt in with `'pluralize' => true`, which uses `Str::plural()` and
  respects `Pluralizer::useLanguage()`.
- `#[Resource('people')]` overrides the segment for one controller.

### Singletons

`#[Singleton]` marks a controller whose resource has no identifier. Its member
actions drop the leaf placeholder (`/profile`, `/profile/edit`). The package
never infers singletons.

### Inherited actions (base CRUD controllers)

A method is a route only when it is declared in the controller itself, or in
a parent class or trait marked `#[Discoverable]`.

```php
#[Discoverable]
abstract class CrudController
{
    public function index() {}
    public function store() {}
    public function show(string $id) {}
    public function destroy(string $id) {}
}

#[Except('destroy')]
class BukuController extends CrudController {}
```

- Unmarked parents and traits, including `Illuminate\Routing\Controller`,
  `AuthorizesRequests`, and `ValidatesRequests`, are never scanned.
- `#[Only(...)]` and `#[Except(...)]` on the child filter inherited and own
  actions, mirroring `Route::resource()->only()/except()`.
- Class-level attributes such as `#[Middleware]` and `#[Where]` apply to
  children from any parent class, marked or not; `#[Discoverable]` only decides
  which methods become routes. Method attributes follow PHP rules: an
  overriding method uses only its own attributes.
- Placeholders still come from parameter names. A shared `show(string $id)`
  produces `{id}` for every child. Per-child placeholder names are out of scope
  for v3.0.

## Attributes

| Attribute | Target | Purpose |
| --- | --- | --- |
| `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]` | method, repeatable | Verb, with optional `uri` and `name` |
| `#[Middleware]` | class, method, repeatable | Append middleware |
| `#[IgnoreParentMiddleware]` | class, method | Drop inherited middleware |
| `#[Where]` | class, method, repeatable | Parameter constraint |
| `#[Resource]` | class | Override the URI segment and name segment |
| `#[Singleton]` | class | Resource without an identifier |
| `#[Discoverable]` | parent class, trait | Opt inherited methods into discovery |
| `#[Only]`, `#[Except]` | class | Filter actions |
| `#[ScopeBindings]`, `#[WithoutScopedBindings]` | class, method | Laravel scoped bindings |
| `#[DoNotDiscover]` | class, method | Exclude |
| `#[LocalOnly]` | class, method | Register only in the local environment |

Authorization stays Laravel middleware, for example `#[Middleware('can:update,buku')]`.

A verb attribute `uri` starting with `/` is relative to the group prefix and
ignores folder segments (replaces v2 `absolute: true`).

## Configuration

```php
return [
    'enabled' => true,

    // null: strict in local and testing, lenient elsewhere.
    'strict' => null,

    'groups' => [
        'web' => [
            'paths' => [app_path('Http/Controllers/Web')],
            'middleware' => ['web'],
        ],
        'api' => [
            'paths' => [app_path('Http/Controllers/Api')],
            'prefix' => 'api',
            'name' => 'api.',
            'middleware' => ['api'],
            // 'namespace', 'domain', 'patterns', 'not_patterns', 'pluralize'
        ],
    ],
];
```

Group options are resolved into a value object once. Pipeline stages do not
read global configuration.

## Tooling

- `php artisan router:check` validates discovery and exits non-zero on
  warnings or errors. It is meant for CI.
- Use Laravel's `route:list` (including `--json` and `--path`) for listing.

## Removed in v3

| v2 | v3 replacement |
| --- | --- |
| `#[Route(method: ...)]` | Verb attributes |
| `#[Route(middleware: ...)]` | `#[Middleware]` |
| `#[Route(uri: ..., name: ...)]` on methods | Verb attribute `uri` and `name` |
| `#[Route(uri: ..., name: ...)]` on classes | `#[Resource]` |
| `#[Route(absolute: true)]` | Verb attribute `uri` starting with `/` |
| `#[Route(keepOrder: true)]` | Explicit verb attribute `uri` |
| `#[Route(scopeBindings: ...)]` | `#[ScopeBindings]`, `#[WithoutScopedBindings]` |
| `convention`, `http_methods_map` | Resource conventions and verb attributes |
| `method_extends` | `#[Discoverable]` on the parent |
| `strict_naming`, `report_skipped_routes` | `strict` and `router:check` |
| `router:list` | `route:list` |
| `router:diagnose` | `router:check` |
| `DiscoveredRoutes`, manifest, fingerprint, provenance | Laravel route collection |

## Issue triage

| Issue | Decision |
| --- | --- |
| #6 registry and manifest | Close. Removed in v3. |
| #7 JSON and group listing | Close. Use `route:list --json`; severity exit codes move to `router:check`. |
| #8 Wayfinder gate | Keep. |
| #9 resourceful URI strategy | Keep as the default, not opt-in. |
| #10 cardinality inference | Close. Replaced by `#[Singleton]`. |
| #11 pluralization overrides | Reduce to the `pluralize` flag and `#[Resource]`. |
| #12 `#[Middleware]` | Keep. |
| #13 `#[Can]` | Close. Use `#[Middleware('can:...')]`. |
| #14 verb attributes | Keep as the primary API. |

## Implementation order

Each step is a separate commit with its tests and docs.

1. Remove the registry, manifest, and `router:list`.
2. Add verb attributes and `#[Middleware]`; remove `#[Route]`.
3. Make resource conventions the default; remove `convention` and `http_methods_map`.
4. Add `#[Discoverable]`, `#[Only]`, and `#[Except]`; remove `method_extends`;
   stop discovering methods from unmarked traits.
5. Add `#[Singleton]`, `#[Resource]`, and the `pluralize` group option.
6. Resolve groups into value objects, unify diagnostics, add `strict` defaults
   and `router:check`.
7. Write the v2 to v3 upgrade guide.
