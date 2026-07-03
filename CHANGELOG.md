# Changelog

## Unreleased

- Added class-level `#[Route(keepOrder: true)]` support so every route method in a controller can preserve parameter order without repeating the attribute.
- Added skipped-route diagnostics for public methods that do not match the `prefix` routing convention.
- Tightened prefix route verb detection so lowercase method names like `getaway` are not mistaken for HTTP verb-prefixed actions.
- Cached route attribute lookups during discovery to reduce repeated reflection work across pipeline stages.
- Fixed the method extension heading in the published router configuration.
- Reorganized documentation into focused guides while keeping the README concise.

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
