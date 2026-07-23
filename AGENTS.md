## Overview
This repository hosts `magicsunday/webtrees-module-base` — a shared PHP library consumed by `webtrees-fan-chart`, `webtrees-pedigree-chart`, and `webtrees-descendants-chart`. It contains the common processors (Date, Name, Image, Place), models (Symbols, NameAbbreviation, PlaceStyle, PlaceFormatSpec, PlaceFormatChoice), locale support (`IsoCountryMap`), and module helpers (VersionInformation) those modules use to render genealogy charts. No JavaScript, no asset pipeline — pure PHP.

Not all of this is consumed by every chart today. The place subsystem (`PlaceProcessor`, the `PlaceStyle` / `PlaceFormatSpec` / `PlaceFormatChoice` models, `IsoCountryMap`) and the compact, generation-aware date API (`DateProcessor`'s `getFormatted*` / `get*Full` methods, `CompactDateFormat`, `Symbols`) are currently used only by the fan chart; the pedigree and descendants charts consume just the shared core (name/image processing and `DateProcessor`'s legacy locale-aware `getBirthDate` / `getDeathDate` / `getLifetimeDescription`). These fan-only components are kept in the base as deliberate pre-investment for a future second consumer, not because all three charts use them today.

## Setup/env
- PHP 8.3 - 8.5 with extensions `dom`, `intl` and `mbstring` is required; composer installs dependencies into `.build/vendor` and binaries into `.build/bin`.
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

This package requires `fisharebest/webtrees` for its API — to develop and test
against, and at runtime to drive on the host install (for example
`Module/VersionInformation`'s release-version check runs on webtrees' bundled
`guzzlehttp/guzzle`). It is a **library consumed into** an administrator's own
webtrees install, and the release artifact ships this package's `src/` alone, so
it neither vendors nor distributes webtrees' transitive dependencies.
`guzzlehttp/guzzle` is one of those: exact-pinned by each webtrees release, not
chosen by us, not patchable by us, and upgraded by the administrator's webtrees —
not by this package. This package declares no direct `guzzle` constraint, so the
host webtrees governs its version even though this package now drives it at
runtime.

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
- Individual checks: `composer ci:test:php:phpstan`, `composer ci:test:php:unit`, `composer ci:test:php:cgl`, `composer ci:test:php:rector`, `composer ci:test:php:lint`, `composer ci:test:php:psr4`.
- Single PHPUnit test: `composer ci:test:php:unit -- --filter TestClassName`.
- Auto-fix: `composer ci:cgl` (PHP-CS-Fixer), `composer ci:rector` (Rector).
- No Makefile: this library has no JS build, no translation catalogue and no release artifact, so every command it needs is a composer script run through the buildbox (see above). To force a full regeneration, delete the generated paths directly: `rm -rf .build node_modules package.json package-lock.json`.

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
  bootstrap.php   — harness only; belongs to no source class, so it stays at the root
  Architecture/   — phpat rule providers, not PHPUnit tests (see the mirror exception below)
  <mirror of src/> — every *Test.php sits at the path its subject has under src/
                     (src/Processor/DateProcessor.php → tests/Processor/DateProcessorTest.php)
```

### Processors
- **`DateProcessor`** — generation-aware date formatting. Public methods include both the legacy locale-aware API (`getBirth*`, `getDeath*`, `getLifetimeDescription`) and the newer compact format API (`getFormatted*`, `get*Full`, `getCompactLifetimeDescription`) that the fan chart uses to keep deep-generation labels short. Marriage dates are served by the compact API only; the legacy `getMarriageDate()` / `getMarriageDateOfParents()` were removed in 3.0.0 (no consumer called them). `getBirthDate()` / `getDeathDate()` return **plain text designed for a text sink** (SVG `<text>`, a JS string): the accessor strips webtrees' display markup and decodes its entities, so a consumer rendering the value into HTML must escape it at that sink.
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
- **`Support/Locale/IsoCountryMap`** — maps free-text country names from GEDCOM PLAC lines to ISO-3166-1 alpha-2 codes, built on `ext-intl` (`Locale::getDisplayRegion`), the reason this library requires the `intl` extension, and on `mbstring` for folding the ICU display names. Used by `PlaceProcessor`'s `CityCountry`, `CityIso2` and `CityIso3` styles.

### Modules
- **`Module/VersionInformation`** — checks GitHub releases for newer module versions, with file cache. No chart module references it directly; the library's `Traits/ModuleCustomTrait::customModuleLatestVersion()` override instantiates it (invoked by the webtrees control panel), so it is not dead code despite having no direct consumer reference.

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
- Strict PHPStan (level max + strict-rules + deprecation-rules + phpunit extension + phpat architecture rules) — no baseline file; findings are fixed in code, with a small set of scoped `ignoreErrors` entries in `phpstan.neon` for irreducible library-export false positives (e.g. `trait.unused` on traits only consumed by downstream modules).
- Architecture rules live in `tests/Architecture/ArchitectureTest.php` (phpat, run inside the PHPStan step): the leaf layers (`Model`, `Support`, `Contract`, `Module`) depend on nothing else under `src/`, `Processor` depends only on those leaves, and `Model`/`Support`/`Processor`/`Module` are `final`. phpat cannot subject a trait, so `Facade`/`Traits` are pinned only as dependency targets — verify a new rule by breaking it, never assume a green run enforces it.
- Promoted constructor properties + `readonly` where applicable (Rector applies this automatically per `rector.php` set list).
- The test tree mirrors `src/`: a test lives at the path of its subject (`src/Support/Locale/IsoCountryMap.php` → `tests/Support/Locale/IsoCountryMapTest.php`) and its namespace carries the matching suffix (`MagicSunday\Webtrees\ModuleBase\Test\Support\Locale`). PSR-4 resolves this without a composer change. PHPUnit itself never checks the namespace — it requires the file and matches the class's *short* name against the filename — so a mis-namespaced test still runs and the test count will not reveal the mistake. `composer ci:test:php:psr4` (`dump-autoload --optimize --strict-psr`; Composer requires the optimisation for the strict check) is the gate that does: it exits non-zero and names the offending file. It runs inside `ci:test` and as its own CI step.
  - **Exception — files that test no `src/` subject.** The mirror rule pairs a test with the class it exercises, so it applies only to files that have one. Two do not, and they stay put: `tests/bootstrap.php` (harness) and `tests/Architecture/` (phpat rule providers — they are not PHPUnit tests at all, are excluded from the suite in `phpunit.xml`, and are consumed by PHPStan via the `phpat.test` service tag; there is no `src/Architecture/` for them to mirror). Their namespace still follows the directory, so the PSR-4 gate covers them.
- Test classes are declared `final`.
- Mutation testing runs through `infection` (`composer ci:test:php:infection`, config in `infection.json5`, `src/` only). It is **not** part of the `ci:test` aggregate: that aggregate is what the pre-commit hook runs over the whole worktree, and a full mutation run is too slow to pay on every commit. The dedicated `Mutation Testing` CI step is therefore the *only* place it runs — do not remove it assuming the aggregate covers it. It is gated to one matrix leg (the score is a property of the tests, not of the PHP version) and needs a coverage driver, which `setup-php` provides via `coverage: pcov`.
- `minMsi` / `minCoveredMsi` **gate at 80**, measured with `timeoutsAsEscaped: true`. Infection counts a timed-out mutant as *killed* by default, which inflates the score — a mutation that made the suite hang is not one the tests caught; with it counted honestly the baseline at adoption is **MSI 82.29 %** at **100 % mutation code coverage** (446 mutants, 367 killed, 74 escaped, 5 timed out). The floor sits below that because the timeout count varies with machine load and now feeds the score. There is deliberately no `maxTimeouts` hard limit on top: it would gate on that same non-deterministic quantity a second time without the surrounding score to absorb it. Raise the floor as the score improves — and never lower it to admit a regression (fix the tests instead; escaped mutants are tracked in their own issue).
- **The CI matrix is coupled three ways** — change it in one place and you must change all three in the same commit: the `php:` list, the repo's `required_status_checks.contexts` on `main` (`build (8.3)` / `build (8.4)` / `build (8.5)`), and the `mutation: true` matrix include that selects the single leg the mutation gate runs on. The job's `name:` is pinned to `build (${{ matrix.php }})` precisely so the extra `mutation` dimension does not rename those contexts. Miss the contexts and PRs hang `BLOCKED` while all checks are green; miss the include and the mutation gate silently runs nowhere.
- Every test declares its coverage target: `#[CoversClass]` for a class/enum subject, `#[CoversTrait]` for a trait, plus `#[UsesClass]` / `#[UsesTrait]` for each collaborator it actually executes (a collaborator outside the `<source>` scope — `src/` only — such as a webtrees vendor class, needs none: it records no coverage). A purely structural test that executes no production line (reflection-only API-shape checks) declares `#[CoversNothing]` rather than a `Covers*` it never exercises — honest, and it does not masquerade as coverage a mutation run would expect to kill. `phpunit.xml` sets `requireCoverageMetadata="true"`, so `composer ci:test:php:unit` fails a test that declares none (as *risky*) — this holds without collecting coverage, so the existing CI unit step enforces it.
- All code comments in English (planning docs may be German).

## Tooling parity with chart modules
Per project policy, `composer.json`, `phpstan.neon`, `rector.php`, `phpunit.xml`, `.phplint.yml`, `.php-cs-fixer.dist.php`, and `.github/workflows/ci.yml` are kept structurally identical (modulo PHP-only vs JS sections) to the canonical fan-chart equivalents at `/volume2/docker/webtrees/app/vendor/magicsunday/webtrees-fan-chart/`. When updating tooling here, mirror the change to fan/ped/des in the same session, and vice versa.

**What this rule is for, and its one exception.** The point is that a change to a config *the repos already share* must not land in one of them and be forgotten in the others — silent drift. It is not a ban on ever being ahead. **Adopting a new dev tool may run as a tracked pilot**: land it in one repo, and in the *same session* file an issue against each remaining repo describing the setup to mirror. That is the opposite of silent drift — the divergence is deliberate, visible and assigned. It is also how the shared config gets to be *right*: both phpat and infection had their leg-selection and score-accounting corrected during review of the pilot, and neither correction had to be repeated three times. A pilot without those issues filed is just drift, and is not allowed. Precedent: phpat (module-base #37 → fan #279 / pedigree #146 / descendants #135) and infection (#39 → fan #280 / pedigree #147 / descendants #136).

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
- Cache directories live under `.build/cache/` (phpstan, rector, phpunit, php-cs-fixer, phplint); jscpd resolves out of `node_modules/`, installed by the composer `post-install-cmd`/`post-update-cmd` hooks. The canonical "force regeneration" reset is `rm -rf .build node_modules package.json package-lock.json`, followed by a fresh `composer update` in the buildbox — note that this discards the installed dependencies too, so budget for the re-resolve.

## Git flow

- Commit subjects — and the pull-request title — are governed by the shared `commit-convention` gate; the normative rule and its full rationale live in `magicsunday/.github/.github/workflows/commit-convention.yml@main`, which self-tests a decision table before applying it. In short: a `GH-`-prefixed subject must match `^GH-\d+: [A-Z]`, every other subject `^[A-Z]` — a capitalised English imperative — and conventional-commit prefixes (`feat:`, `Fix:`, …) as well as path-like starts (`src/…: …`) are rejected whatever their case. It runs on every pull request via `.github/workflows/commit-lint.yml`, advisory until `commit-convention / Commit convention` is a required context in branch protection.
- Branches for an issue are named exactly `GH-<N>`; the `GH-<N>: ` prefix marks work that belongs to that issue, so a drive-by fix on the branch keeps its own unprefixed subject.
- The pull-request body closes the issue with `Closes #<N>` — the `GH-<N>: ` subject prefix is not a GitHub link and closes nothing.
- Never add a `Co-Authored-By:` trailer or any other AI attribution.
