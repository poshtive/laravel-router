# Configuration

`enabled` disables all automatic discovery when false. Each `groups` entry supports `paths`, `prefix`, `name`, `middleware`, `domain`, `namespace`, `patterns`, `not_patterns`, and `pluralize` (default `false`; see [Route Discovery](route-discovery.md#resource-actions)).

Paths are scanned in sorted order. `paths` may contain multiple directories; each path uses the configured namespace as the namespace of the controllers directly inside it. `patterns` and `not_patterns` are Symfony Finder filename patterns and are useful for excluding tests, generated controllers, or internal files.

```php
'groups' => [
    'web' => [
        'paths' => [app_path('Http/Controllers/Web')],
        'middleware' => ['web'],
    ],
    'api' => [
        'paths' => [app_path('Http/Controllers/Api')],
        'prefix' => 'api/v1',
        'name' => 'api.v1.',
        'middleware' => ['api'],
        'domain' => '{tenant}.example.test',
    ],
],
```

For `app/Http/Controllers/Api/UserController.php`:

```php
class UserController
{
    public function index() {}
}
```

the API group registers `GET https://{tenant}.example.test/api/v1/user` with route name `api.v1.user.index` and the `api` middleware group.

HTTP verbs follow Laravel's resource conventions (`store` is POST, `update` is PUT and PATCH, `destroy` is DELETE, everything else is GET). Use a verb attribute such as `#[Post]` to change it. Discovery is skipped when Laravel route cache is loaded.

The service provider merges these defaults, publishes the file, and discovers every configured group during boot. Set `groups` to an empty array only when the package is intentionally installed without automatic discovery.

## Recommended web/API setup

```php
'groups' => [
    'web' => ['paths' => [app_path('Http/Controllers/Web')], 'middleware' => ['web']],
    'api' => [
        'paths' => [app_path('Http/Controllers/Api')],
        'prefix' => 'api', 'name' => 'api.', 'middleware' => ['api'],
    ],
],
```

Paths inside `app/` derive their namespace automatically. For modules and any path outside `app/`, set `namespace` to the namespace of classes directly inside the configured path; without it the path is skipped with a `discovery_namespace_missing` diagnostic. Group `prefix` is a URI prefix only; group `name` is applied to the final generated route name.

## Convention details

See [Route Discovery](route-discovery.md#resource-actions) for the resource action table. Verb attributes take precedence over the conventions.

Only methods written in the controller itself are discovered, plus methods from parent classes or traits marked `#[Discoverable]` (see [Attributes](attributes.md#base-controllers-and-traits)). `IgnoreParentMiddleware` opts a class or method out of inherited middleware.

## Strict mode and diagnostics

Every discovery decision is recorded as a `Diagnostic` with a stable `code`, a
`Severity` (`info`, `warning`, or `error`), a message, and the group, path, and
controller action it concerns.

| Severity | Examples |
| --- | --- |
| `info` | `route_skipped` (`#[DoNotDiscover]`, `#[LocalOnly]`, `#[Only]`/`#[Except]`), `controller_abstract` |
| `warning` | `discovery_path_missing`, `discovery_path_invalid`, `discovery_namespace_missing`, `controller_unloadable`, `action_filter_unknown` |
| `error` | `route_invalid`, `route_duplicate_name`, `route_duplicate_signature` |

`strict` controls what happens on errors:

- `null` (default): strict in the `local` and `testing` environments, lenient elsewhere.
- `true`: any error throws `RouteDiscoveryException` before a single discovered
  route is registered. `$exception->diagnostics()` returns the errors.
- `false`: invalid and duplicate routes are skipped (the first duplicate wins)
  and the application boots with the remaining routes.

Warnings and errors are written to the application log with the diagnostic as
context. Info diagnostics are not logged. Run `php artisan router:check` to list
all of them; it exits non-zero on warnings or errors.
