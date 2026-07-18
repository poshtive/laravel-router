# Discovered Route Registry

The discovered-route registry exposes the immutable result of route discovery as a public contract. Applications can query routes and diagnostics by group and status without filtering Laravel's internal route collection.

## Public API

### `DiscoveredRoutes`

Resolve from the container after discovery has run:

```php
$registry = app(\Poshtive\Router\Discovery\DiscoveredRoutes::class);
```

#### Methods

- **`all(): list<DiscoveredRouteEntry>`** — All route entries regardless of status.
- **`routes(): list<DiscoveredRouteEntry>`** — Only routes with `registered` status.
- **`forGroup(string $name): self`** — Filter entries by discovery group. Returns a new instance.
- **`forStatus(RouteStatus $status): self`** — Filter entries by status. Returns a new instance.
- **`diagnostics(): list<Diagnostic>`** — All structured diagnostics collected during discovery.
- **`count(): int`** — Total number of entries in the current view.
- **`toArray(): array`** — Serialize the registry to an array.
- **`jsonSerialize(): array`** — JSON-serializable representation.

Chaining is supported:

```php
$registry->forGroup('web')->forStatus(RouteStatus::Skipped)->all();
```

### `DiscoveredRouteEntry`

Each entry is an immutable scalar DTO with these fields:

| Field | Type | Description |
|---|---|---|
| `id` | `string` | Stable discovery ID (`hash('xxh32', "{group}\0{controller}\0{method}")`) |
| `group` | `string` | Discovery group name |
| `status` | `RouteStatus` | `registered`, `skipped`, `invalid`, or `discarded` |
| `methods` | `list<string>` | Effective HTTP methods (including HEAD when GET is present) |
| `uri` | `string` | Effective URI |
| `name` | `string` | Effective route name |
| `domain` | `string|null` | Effective domain |
| `controller` | `string` | Fully-qualified class name |
| `method` | `string` | Controller method name |
| `sourceFile` | `string` | Source file relative to `base_path()` |
| `sourceLine` | `int|null` | Method start line from reflection |
| `middleware` | `list<string>` | Applied middleware |
| `wheres` | `array<string, string>` | Parameter constraints |
| `scopeBindings` | `bool` | Scoped binding flag |
| `withoutScopedBindings` | `bool` | Without-scoped-bindings flag |
| `skipReason` | `string|null` | Reason the route was skipped (e.g., `DoNotDiscover`) |
| `invalidReason` | `string|null` | Reason the route was marked invalid |
| `discardReason` | `string|null` | Reason the route was discarded (e.g., duplicate) |
| `provenance` | `list<string>` | Ordered contribution trace of Provenance values |

### `RouteStatus`

String-backed enum:

- `RouteStatus::Registered` — Route was successfully registered.
- `RouteStatus::Skipped` — Route was intentionally skipped (e.g., `DoNotDiscover`, `LocalOnly`).
- `RouteStatus::Invalid` — Route has validation errors.
- `RouteStatus::Discarded` — Route was removed by duplicate detection.

### `Provenance`

String-backed enum describing the origin of a resolved value:

- `convention` — Derived from filesystem convention.
- `strategy` — Derived from HTTP verb prefix strategy.
- `group` — Derived from group configuration (prefix, name).
- `inherited` — Inherited from a parent controller.
- `class_attribute` — Overridden by a class-level `#[Route]` attribute.
- `method_attribute` — Overridden by a method-level `#[Route]` attribute.

### `Diagnostic`

Structured diagnostic DTO:

| Field | Type | Description |
|---|---|---|
| `code` | `string` | Stable diagnostic code |
| `severity` | `string` | `error`, `warning`, or `info` |
| `group` | `string` | Discovery group or empty string |
| `path` | `string` | Relevant path or empty string |
| `message` | `string` | Human-readable message |

## Manifest Lifecycle

### Cached Manifest

The package writes a JSON manifest (`bootstrap/cache/laravel-router-manifest.json`) that preserves the full registry including skipped, invalid, and discarded routes. This is coordinated with Laravel's route cache through a build fingerprint.

### Writing the Manifest

Call the static method after discovery has run:

```php
\Poshtive\Router\RouterServiceProvider::writeManifest();
```

The manifest includes:
- Schema version
- Build fingerprint (SHA-256 of sorted route IDs + package version)
- All route entries serialized as arrays
- All diagnostics serialized as arrays
- Total registered route count
- Zero-route state flag
- Package version

### Reading the Manifest

```php
$manager = new \Poshtive\Router\Discovery\ManifestCacheManager(base_path());
$manifest = $manager->read();
```

`read()` returns `null` for:
- Missing file
- Corrupt JSON
- Schema version mismatch
- Unreadable file

### Removing the Manifest

```php
\Poshtive\Router\RouterServiceProvider::removeManifest();
```

This also cleans up any leftover `.tmp` files.

### Fingerprint

```php
$fingerprint = \Poshtive\Router\Discovery\BuildFingerprint::generate($entries, $packageVersion);
$valid = \Poshtive\Router\Discovery\BuildFingerprint::verify($fingerprint, $entries, $packageVersion);
```

The fingerprint is deterministic regardless of entry order. Use it to verify that a cached manifest matches the current discovery output.

### Failure Modes

- **Missing manifest after `route:cache`**: The registry returns an empty result. Application routing remains valid.
- **Stale manifest**: When the manifest fingerprint does not match cached route metadata, the manifest is rejected.
- **Version mismatch**: A manifest written by a different schema version is rejected.
- **Corrupt manifest**: Invalid JSON or incomplete data returns `null` and triggers a diagnostic.
- **Zero-route projects**: The `hasZeroRoutes` flag explicitly records the zero-route state.
