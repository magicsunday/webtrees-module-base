## Overview
This repository hosts `magicsunday/webtrees-module-base` — a shared PHP library consumed by `webtrees-fan-chart`, `webtrees-pedigree-chart`, and `webtrees-descendants-chart`. It contains the common processors (Date, Name, Image, Place), models (Symbols, NameAbbreviation, PlaceStyle, PlaceFormatSpec, PlaceFormatChoice), locale support (`IsoCountryMap`), and module helpers (VersionInformation) those modules use to render genealogy charts. No JavaScript, no asset pipeline — pure PHP.

## Setup/env
- PHP 8.3 - 8.5 with extensions `dom` and `intl` is required; composer installs dependencies into `.build/vendor` and binaries into `.build/bin`.
- The most common dev workflow is via `make link-base` from a sibling chart module: that symlinks `.build/vendor/.../webtrees-module-base` in the chart module to a sibling clone of this repo, so edits here are immediately picked up by the consumer.

## Running the test suite

This library has no module-local container. PHP runs through the webtrees-docker
buildbox, which provides ext-intl (required since 3.0.0):

    cd /path/to/webtrees-docker && docker compose run --rm buildbox bash -c \
        'cd /var/webtrees/app/vendor/magicsunday/webtrees-module-base && composer ci:test'

Substitute `composer install`, `composer update` or `composer ci:cgl` for the last
command as needed. CI does not use Docker at all — the workflow installs PHP
directly via setup-php.

### Why `config.policy.advisories.ignore` lists `guzzlehttp/guzzle`

`composer.json` carries no comments, so the rationale lives here — and the entry
mirrors the identical one in the chart modules and webtrees-statistics.

This package requires `fisharebest/webtrees` only to develop and test against its
API. It is a **library consumed into** an administrator's own webtrees install,
and the release artifact ships this package's `src/` alone, so it neither vendors
nor distributes webtrees' transitive dependencies. `guzzlehttp/guzzle` is one of
those: exact-pinned by each webtrees release, not chosen by us, not patchable by
us, and upgraded by the administrator's webtrees — not by this package.

Composer's advisory policy blocks *resolution*, not merely the `composer audit`
report (`--no-audit` / `COMPOSER_NO_AUDIT` suppress only the report). So whenever
a pinned guzzle picks up an advisory, every install and CI run of this package
reds for a vulnerability that cannot reach anyone through us.

The ignore is deliberately **scoped to that one package** rather than disabling
the policy wholesale: advisories on any other package still block resolution,
guzzle CVEs still surface in `composer audit`, and this package's own direct
dependencies stay subject to review as usual. `config` applies only to the root
package, so consumers are unaffected.

## Build & tests
- **`composer ci:test` MUST run before every commit** — catches phplint, PHPStan (level max), Rector, PHPUnit, and PHP-CS-Fixer issues before they reach GitHub CI.
- Individual checks: `composer ci:test:php:phpstan`, `composer ci:test:php:unit`, `composer ci:test:php:cgl`, `composer ci:test:php:rector`, `composer ci:test:php:lint`.
- Single PHPUnit test: `composer ci:test:php:unit -- --filter TestClassName`.
- Auto-fix: `composer ci:cgl` (PHP-CS-Fixer), `composer ci:rector` (Rector).
- Make shortcuts: `make clean` (remove `.build/` and `node_modules/`, plus the npm `package.json`/`package-lock.json` artifacts). All other quality-gate commands run through `composer` directly (see above).

## Architecture

### Layout
```
src/
  Contract/       — local interfaces required by processors (e.g. ModuleAssetUrlInterface)
  Facade/         — traits for chart DataFacade implementations (module/route injection)
  Model/          — value objects + enums (Symbols, NameAbbreviation, PlaceStyle, PlaceFormatSpec, PlaceFormatChoice)
  Module/         — module-level helpers (VersionInformation)
  Processor/      — DateProcessor, NameProcessor, ImageProcessor, PlaceProcessor
  Support/        — locale-independent helpers (CompactDateFormat, TextDirection)
  Support/Locale/ — locale-aware helpers (IsoCountryMap)
  Traits/         — shared ModuleCustomTrait / ModuleChartTrait helpers for consuming modules
tests/
  *Test.php       — PHPUnit tests, namespace MagicSunday\Webtrees\ModuleBase\Test
```

### Processors
- **`DateProcessor`** — generation-aware date formatting. Public methods include both the legacy locale-aware API (`getBirth*`, `getDeath*`, `getMarriage*`) and the newer compact format API (`getFormatted*`, `getCompactLifetimeDescription`) that chart modules use to keep deep-generation labels short.
- **`NameProcessor`** — name extraction from webtrees name HTML. Splits first/last/preferred names, handles starredname spans, alternative names, married names. DOM/XPath based.
- **`ImageProcessor`** — highlight image + silhouette URL resolution. Constructor requires `ModuleCustomInterface & ModuleAssetUrlInterface` (the marker interface ensures the module exposes `assetUrl()`, which lives on `ModuleCustomTrait` and is invisible to the `ModuleCustomInterface` type alone).
- **`PlaceProcessor`** — extracts and shortens birth/death/marriage place names for chart labels, driven by a resolved `PlaceFormatSpec`. Supports `PlaceStyle::Full` (unchanged), `PlaceStyle::Levels` (keep a fixed number of hierarchy levels, from either end), and `PlaceStyle::CityCountry` (keep the first and last segment; resolves the country segment via `IsoCountryMap` when it is a recognised country name).

### Models
- **`Model/Symbols`** — backed enum for genealogical symbols (Birth ★, Death †, en-dash separator, MarriageDateUnknown sentinel).
- **`Model/NameAbbreviation`** — backed enum + `resolve()` helper for the chart-label name-abbreviation strategy (auto / given / surname). Each chart module wires its own NodeData class; this base library only ships the strategy enum.
- **`Model/PlaceStyle`** — enum for the axis along which a place name is shortened (`Full`, `Levels`, `CityCountry`). Distinct from `PlaceFormatChoice`: "Automatic" is a settings source, resolved before it reaches a formatter.
- **`Model/PlaceFormatSpec`** — final readonly value object holding a fully resolved place-formatting instruction (`style`, `levels`, `fromEnd`) with no configuration lookups left to perform; validates the level count in its constructor.
- **`Model/PlaceFormatChoice`** — backed enum for the place-detail options a module offers in its configuration and the value persisted in the module preference. Deliberately free of display labels — the consuming module supplies those at its own `I18N::translate()` call sites.

### Support
- **`Support/CompactDateFormat`** — derives a locale-aware, compact (numeric) date format string from the CLDR/ICU short-date pattern of a locale, for `DateProcessor`'s compact API.
- **`Support/TextDirection`** — resolves script direction (LTR/RTL) for arbitrary strings.
- **`Support/Locale/IsoCountryMap`** — maps free-text country names from GEDCOM PLAC lines to ISO-3166-1 alpha-2 codes, built on `ext-intl` (`Locale::getDisplayRegion`), the reason this library requires the `intl` extension. Used by `PlaceProcessor`'s `CityCountry` style.

### Modules
- **`Module/VersionInformation`** — checks GitHub releases for newer module versions, with file cache. Used by all three chart modules' admin pages.

### Contracts
- **`Contract/ModuleAssetUrlInterface`** — marker interface declaring `assetUrl(string $asset): string`. Custom modules that use webtrees' `ModuleCustomTrait` already satisfy this method via the trait; consumers add `implements ModuleAssetUrlInterface` to their `Module` class so type narrowing works for `ImageProcessor`'s constructor.

### Facade
- **`Facade/ModuleAwareDataFacadeTrait`** — injects the owning module (for asset URLs, custom module metadata) into a chart DataFacade implementation.
- **`Facade/RouteAwareDataFacadeTrait`** — extends `ModuleAwareDataFacadeTrait` with a route reference and a canonical `chartUrl()` builder for consumers that drive their AJAX updates through the chart's own routed URL.

### Traits
- **`Traits/ModuleChartTrait`** — shared chart-module helpers on top of webtrees' own `ModuleChartTrait`; consuming classes must define a `ROUTE_DEFAULT` class constant.
- **`Traits/ModuleCustomTrait`** — shared `ModuleCustomInterface` helpers on top of webtrees' own `ModuleCustomTrait`; consuming classes must define `CUSTOM_*` constants and `resourcesFolder()`.

## Code style
- PSR-12 + PER-CS 2.x with project-specific tightenings (PHP-CS-Fixer config in `.php-cs-fixer.dist.php`).
- All files declare `strict_types=1`.
- Strict PHPStan (level max + strict-rules + deprecation-rules + phpunit extension) — no baseline file; findings are fixed in code, with a small set of scoped `ignoreErrors` entries in `phpstan.neon` for irreducible library-export false positives (e.g. `trait.unused` on traits only consumed by downstream modules).
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
- `composer ci:test` runs phpstan with `level: max`. Never use `@phpstan-ignore` annotations — fix the code, or add a scoped `ignoreErrors` entry in `phpstan.neon` keyed by identifier and path with a rationale comment (see the `trait.unused` entries there for the pattern).
- `assetUrl()` lives on `ModuleCustomTrait`, not on any interface. Anywhere this library needs it, the parameter type uses an intersection with `ModuleAssetUrlInterface` (see `Contract/`). Never use `method_exists` to work around missing-method type errors.
- PHPUnit 12 prefers `self::createStub()` over `$this->createMock()` for tests that only need a target object for reflection-based access (no mock-call expectations).
- Cache directories live under `.build/cache/` (phpstan, rector, phpunit, php-cs-fixer, phplint); jscpd resolves out of `node_modules/`, installed by the composer `post-install-cmd`/`post-update-cmd` hooks. `make clean` is the canonical "force regeneration" reset — it removes both `.build/` and `node_modules/` (plus the npm `package.json`/`package-lock.json` artifacts).
