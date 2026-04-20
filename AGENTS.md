## Overview
This repository hosts `magicsunday/webtrees-module-base` — a shared PHP library consumed by `webtrees-fan-chart`, `webtrees-pedigree-chart`, and `webtrees-descendants-chart`. It contains the common processors (Date, Name, Image, Place), models (Node, Symbols), and module helpers (VersionInformation) those modules use to render genealogy charts. No JavaScript, no asset pipeline — pure PHP.

## Setup/env
- PHP 8.3 - 8.5 with extension `dom` is required; composer installs dependencies into `.build/vendor` and binaries into `.build/bin`.
- All PHP tooling normally runs inside the parent webtrees Docker buildbox — never directly on the NAS or in phpfpm:
  ```
  cd /volume2/docker/webtrees && make bash
  cd app/vendor/magicsunday/webtrees-module-base
  ```
- For standalone development (clone outside the parent webtrees repo), `compose.yaml` provides a `php` service (composer:2 image). Use `make ci-test` / `make bash` for the standalone flow.
- The most common dev workflow is via `make link-base` from a sibling chart module: that symlinks `.build/vendor/.../webtrees-module-base` in the chart module to a sibling clone of this repo, so edits here are immediately picked up by the consumer.

## Build & tests
- **`composer ci:test` MUST run before every commit** — catches phplint, PHPStan (level max), Rector, PHPUnit, and PHP-CS-Fixer issues before they reach GitHub CI.
- Individual checks: `composer ci:test:php:phpstan`, `composer ci:test:php:unit`, `composer ci:test:php:cgl`, `composer ci:test:php:rector`, `composer ci:test:php:lint`.
- Single PHPUnit test: `composer ci:test:php:unit -- --filter TestClassName`.
- Auto-fix: `composer ci:cgl` (PHP-CS-Fixer), `composer ci:rector` (Rector).
- Regenerate phpstan baseline (after intentional changes): `composer ci:test:php:phpstan:baseline` — should remain empty (no findings) under normal circumstances.
- Make shortcuts (standalone clone): `make install`, `make ci-test`, `make ci-cgl`, `make ci-rector`, `make ci-phpstan-baseline`, `make bash`.

## Architecture

### Layout
```
src/
  Contract/   — local interfaces required by processors (e.g. ModuleAssetUrlInterface)
  Model/      — value objects + enums (Symbols)
  Module/     — module-level helpers (VersionInformation)
  Processor/  — DateProcessor, NameProcessor, ImageProcessor, PlaceProcessor
tests/
  *Test.php   — PHPUnit tests, namespace MagicSunday\Webtrees\ModuleBase\Test
```

### Processors
- **`DateProcessor`** — generation-aware date formatting. Public methods include both the legacy locale-aware API (`getBirth*`, `getDeath*`, `getMarriage*`) and the newer compact format API (`getFormatted*`, `getCompactLifetimeDescription`) that chart modules use to keep deep-generation labels short.
- **`NameProcessor`** — name extraction from webtrees name HTML. Splits first/last/preferred names, handles starredname spans, alternative names, married names. DOM/XPath based.
- **`ImageProcessor`** — highlight image + silhouette URL resolution. Constructor requires `ModuleCustomInterface & ModuleAssetUrlInterface` (the marker interface ensures the module exposes `assetUrl()`, which lives on `ModuleCustomTrait` and is invisible to the `ModuleCustomInterface` type alone).
- **`PlaceProcessor`** — place name shortening (configurable parts) for chart labels.

### Models
- **`Model/Symbols`** — backed enum for genealogical symbols (Birth ★, Death †, etc.) plus the `MARRIAGE_DATE_UNKNOWN` sentinel.
- **`Model/Node`, `Model/NodeData`** — used by chart modules for tree-node serialization to D3 (each module wraps these in its own DataFacade).

### Modules
- **`Module/VersionInformation`** — checks GitHub releases for newer module versions, with file cache. Used by all three chart modules' admin pages.

### Contracts
- **`Contract/ModuleAssetUrlInterface`** — marker interface declaring `assetUrl(string $asset): string`. Custom modules that use webtrees' `ModuleCustomTrait` already satisfy this method via the trait; consumers add `implements ModuleAssetUrlInterface` to their `Module` class so type narrowing works for `ImageProcessor`'s constructor.

## Code style
- PSR-12 + PER-CS 2.x with project-specific tightenings (PHP-CS-Fixer config in `.php-cs-fixer.dist.php`).
- All files declare `strict_types=1`.
- Strict PHPStan (level max + strict-rules + deprecation-rules + phpunit extension) — the baseline must remain empty.
- Promoted constructor properties + `readonly` where applicable (Rector applies this automatically per `rector.php` set list).
- Test classes namespace `MagicSunday\Webtrees\ModuleBase\Test`.
- All code comments in English (planning docs may be German).

## Tooling parity with chart modules
Per project policy, `composer.json`, `phpstan.neon`, `rector.php`, `phpunit.xml`, `.phplint.yml`, `.php-cs-fixer.dist.php`, and `.github/workflows/ci.yml` are kept structurally identical (modulo PHP-only vs JS sections) to the canonical fan-chart equivalents at `/volume2/docker/webtrees/app/vendor/magicsunday/webtrees-fan-chart/`. When updating tooling here, mirror the change to fan/ped/des in the same session, and vice versa.

## Release
- Library — no asset zip, no `make release` pipeline. Releases are pure git tag + GitHub release.
- Bump consumer-facing dependencies first (e.g. when bumping `php` constraint or changing public class/interface signatures, decide on minor vs major per semver).
- After tagging, the three chart-module `composer.json` files need their `magicsunday/webtrees-module-base` constraint widened to allow the new range (e.g. `"^1.1 || ^2.0"`), then those modules ship patch releases.
- Tag/release commands:
  ```
  git tag <X.Y.Z>
  git push origin main --tags
  gh release create <X.Y.Z> --title "<X.Y.Z>" --notes-file /path/to/notes.md
  ```

## Common pitfalls
- `composer ci:test` runs phpstan with `level: max`. Don't add ignoreErrors patterns to `phpstan-baseline.neon` to silence findings — fix the code or use targeted `@phpstan-ignore` annotations with rationale.
- `assetUrl()` lives on `ModuleCustomTrait`, not on any interface. Anywhere this library needs it, the parameter type uses an intersection with `ModuleAssetUrlInterface` (see `Contract/`). Never use `method_exists` to work around missing-method type errors.
- PHPUnit 12 prefers `self::createStub()` over `$this->createMock()` for tests that only need a target object for reflection-based access (no mock-call expectations).
- Cache directories live under `.build/cache/` (phpstan, rector, phpunit, php-cs-fixer, phplint). Wiping `.build/` is the canonical "force regeneration" reset.
