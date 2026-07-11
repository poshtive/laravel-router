# Upgrade guide: v1 to v2

Move `Router::create()->discover(...)` calls out of route files and configure equivalent directories under `router.groups`. Group `prefix`, `name`, middleware, domain, namespace, and file patterns now live in configuration.

Public methods are routes by default under `attribute_or_get`. `DoNotDiscover` can now exclude individual methods. Class and method URI/name overrides preserve nested parent segments, and `prefix` is reserved for discovery groups.

Manual routes remain supported. Clear and rebuild Laravel's route cache after changing discovery configuration with `php artisan route:clear` and `php artisan route:cache`.

## Migration checklist

1. Publish `config/router.php` and set `enabled` to true.
2. Add one group for every former discovery directory.
3. Set an explicit `namespace` for module directories or non-standard controller roots.
4. Move route-file middleware and URL prefixes into the group options.
5. Replace controller `prefix` assumptions with `Route(uri: ...)` where a segment must change.
6. Run `route:list`, the PHPUnit suite, and Pint before deploying.

The manual `Router` factory is no longer part of the v2 API. Route files may still contain ordinary Laravel route declarations, but discovery itself is exclusively configuration-driven.
