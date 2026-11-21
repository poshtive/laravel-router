# Changelog

## Unreleased

- Discovery paths outside `app/` without a `namespace` option are now skipped with a `discovery_namespace_missing` diagnostic instead of guessing a namespace from the folder name.
- Added the `pluralize` group option, which pluralizes folder and controller segments in URIs and route names with Laravel's pluralizer. Segments are used as written by default.
- Fixed nested resources more than one level deep placing parent placeholders after the child segment (`user/{user}/post/comment/{post}` instead of `user/{user}/post/{post}/comment`).
- **Breaking:** Removed `method_extends`. Methods from parent classes and traits are discovered only when the parent or trait is marked `#[Discoverable]`, so framework helpers such as `authorize`, `validate`, and `callAction` can no longer leak as routes. Public methods from unmarked traits no longer become routes.
- Added `#[Only]` and `#[Except]` to filter a controller's actions, including inherited ones.
- **Breaking:** Resource actions follow Laravel's resource conventions by default: `store` is `POST /photo`, `show`, `update` (PUT and PATCH), and `destroy` (DELETE) use `/photo/{photo}`, and `create`/`edit` keep their segment. Other methods remain GET actions.
- **Breaking:** Removed the `convention` option (including the `prefix` convention) and `http_methods_map`. Use verb attributes such as `#[Post]` instead.
- **Breaking:** Replaced `#[Route]` with focused attributes: `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, and `#[Delete]` (with optional `uri` and `name`), repeatable `#[Middleware]`, class-level `#[Resource]`, and `#[ScopeBindings]`/`#[WithoutScopedBindings]`.
- **Breaking:** `absolute: true` is replaced by a `uri` starting with `/`. A class-level absolute URI now keeps method segments for non-`index` actions.
- Conflicting `uri` or `name` values across verb attributes, and combining `#[ScopeBindings]` with `#[WithoutScopedBindings]` on one target, now mark the route invalid.
- **Breaking:** Removed `#[Route(keepOrder: ...)]`. Write an explicit `uri` when parameters must follow the method segment.
- **Breaking:** Removed the `router:list` command. Use Laravel's `route:list`, which supports `--path`, `--name`, and `--json`.
- Added `--fail-on-warning` to `router:diagnose` for severity-based exit codes.
- Added a Laravel Wayfinder compatibility gate that runs `wayfinder:generate` against discovered routes on Laravel 12 and 13 and verifies identical output from the route cache.
- Fixed discovery on Windows when paths mix separators, such as `app_path('Http/Controllers')`, and for paths containing `..`, duplicate, or trailing separators.

## 2.0.1 - 2026-07-18

- Added package-origin route tracking so `router:list` shows only discovered routes by default. Added `--all` flag and separate discovered-route count in `router:diagnose`.
- Added structured diagnostics for missing and invalid discovery paths, surfaced through `router:diagnose`.
- Fixed class-level `#[Route(keepOrder: true)]` being reset when a method-level `#[Route]` omits the `keepOrder` option.
- Fixed automatic enum parameter discovery to only bind string-backed enums; unit enums and int-backed enums are now reported as invalid instead of silently generating unusable route bindings.
- Fixed duplicate route detection to normalize effective route signatures including domain, GET-to-GET+HEAD expansion, and registered URI.

## 2.0.0 - 2026-07-12

- Added Laravel 12 compatibility and CI coverage for Laravel 12 and 13.
- Added strict source typing and a PHPStan level 6 analysis command for safer route discovery changes.
- Expanded the README and examples with an end-to-end API controller and generated route table.
- Fixed discovery validation to reject optional URI parameters that are followed by a required path segment.
- Added config-driven automatic discovery with route groups, prefixes, middleware, domains, namespaces, and file patterns.
- Added deterministic nested URI/name overrides, absolute URIs, method-level `DoNotDiscover`, validation, and route-cache integration.
- Added `router:list` and `router:diagnose` Artisan commands for route inspection and discovery diagnostics.
- Added backed-enum parameter discovery and optional strict route-name validation.
- Added discovery diagnostics to `router:diagnose`, including skipped routes, validation conflicts, and unloadable controllers.
- Added global atomic discovery validation, duplicate registration prevention, optional/custom-key parameter resolution, and scoped binding metadata.
- Fixed default application namespace discovery and reported invalid parameter mappings without crashing non-strict discovery.
- Added an enforced 100% coverage threshold and Laravel route-cache integration coverage.
- Documented the v2 restriction to one URI placeholder per segment.
- Added the v1-to-v2 upgrade guide and configuration, discovery, attribute, and example documentation.
- Fixed class-level absolute URI overrides and rejected unbalanced URI placeholders during validation.

## 1.2.0 - 2026-07-03

- Added class-level `#[Route(keepOrder: true)]` support so every route method in a controller can preserve parameter order without repeating the attribute.
- Added skipped-route diagnostics for public methods that do not match the `prefix` routing convention.
- Tightened prefix route verb detection so lowercase method names like `getaway` are not mistaken for HTTP verb-prefixed actions.
- Cached route attribute lookups during discovery to reduce repeated reflection work across pipeline stages.
- Fixed the method extension heading in the published router configuration.
- Added repository editor and line-ending defaults.
- Reorganized documentation into focused guides while keeping the README concise.
- Refined Composer development dependency constraints and refreshed locked Symfony patch releases.
- Updated GitHub Actions workflow dependencies.

## 1.1.0 - 2026-03-25

- Updated route discovery logic and added support for route attributes.
- Fixed Composer dependency resolution so the committed lock file remains compatible with PHP 8.3.
- Tightened CI validation to check the committed Composer lock file before installing dependencies.

## 1.0.0 - 2026-03-25

- Added strict discovery diagnostics for duplicate route signatures and names.
- Added optional logging for skipped routes discovered through `#[DoNotDiscover]` and `#[LocalOnly]`.
- Made discovered route registration deterministic when priorities tie.
- Added PHPUnit and Orchestra Testbench integration coverage for discovery behavior.
- Added GitHub Actions CI for Composer validation and the PHPUnit test suite.
- Promoted the package documentation and metadata to a stable 1.0 release baseline.
