# Configuration

`enabled` disables all automatic discovery when false. Each `groups` entry supports `paths`, `prefix`, `name`, `middleware`, `domain`, `namespace`, `patterns`, and `not_patterns`.

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

HTTP verbs follow Laravel's resource conventions (`store` is POST, `update` is PUT and PATCH, `destroy` is DELETE, everything else is GET). Use a verb attribute such as `#[Post]` to change it. `strict` turns validation and duplicate diagnostics into exceptions. Discovery is skipped when Laravel route cache is loaded.

Set `report_skipped_routes` to true to send exclusion and resolver decisions to the application logger.

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

For modules, set `namespace` to the namespace of classes directly inside the configured path. Group `prefix` is a URI prefix only; group `name` is applied to the final generated route name.

## Convention details

See [Route Discovery](route-discovery.md#resource-actions) for the resource action table. Verb attributes take precedence over the conventions.

Only methods written in the controller itself are discovered, plus methods from parent classes or traits marked `#[Discoverable]` (see [Attributes](attributes.md#base-controllers-and-traits)). `IgnoreParentMiddleware` opts a class or method out of inherited middleware.

In non-strict mode invalid or duplicate definitions are logged and the first duplicate definition is registered while later duplicates are skipped. Strict mode throws `RouteDiscoveryException` before any discovery route is registered for invalid HTTP methods, malformed placeholders, duplicate names, or duplicate method/URI signatures across all configured groups and paths.

Set `strict_naming` to true when every discovered route must have a non-empty name.
