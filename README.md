[![Latest version](https://img.shields.io/github/v/release/magicsunday/webtrees-module-base?sort=semver)](https://github.com/magicsunday/webtrees-module-base/releases/latest)
[![License](https://img.shields.io/github/license/magicsunday/webtrees-module-base)](https://github.com/magicsunday/webtrees-module-base/blob/main/LICENSE)
[![CI](https://github.com/magicsunday/webtrees-module-base/actions/workflows/ci.yml/badge.svg)](https://github.com/magicsunday/webtrees-module-base/actions/workflows/ci.yml)

# webtrees-module-base

Shared PHP base classes for the [magicsunday](https://github.com/magicsunday) family of [webtrees](https://www.webtrees.net) chart modules. Centralises the name, image and date processing logic, common models, and module helpers (GitHub release-version checking with file cache) so the chart modules do not have to reimplement the shared pieces.

This package ships no UI of its own — it is consumed as a Composer dependency by:

- [webtrees-fan-chart](https://github.com/magicsunday/webtrees-fan-chart) — SVG ancestor fan chart
- [webtrees-pedigree-chart](https://github.com/magicsunday/webtrees-pedigree-chart) — SVG pedigree chart
- [webtrees-descendants-chart](https://github.com/magicsunday/webtrees-descendants-chart) — SVG descendants chart

> **Scope note:** not every base component is consumed by all three charts yet. The place-name subsystem (`PlaceProcessor`, its `PlaceFormat*` / `PlaceStyle` models and `IsoCountryMap`) and the compact, generation-aware date API (`DateProcessor`'s `getFormatted*` / `get*Full` methods, `CompactDateFormat` and the `Symbols` enum) are currently used only by the fan chart; the pedigree and descendants charts consume just the shared core (name/image processing and `DateProcessor`'s legacy locale-aware methods). These fan-only pieces live in the base as deliberate pre-investment, so a second chart can adopt them without a namespace move.

## Requirements

- PHP 8.3 - 8.5 with extensions `dom`, `intl` and `mbstring`
- [webtrees](https://www.webtrees.net/) `~2.2`

## Installation

This package is pulled in automatically when you install any of the chart modules above. To depend on it directly from your own webtrees module:

```shell
composer require magicsunday/webtrees-module-base
```

If your module uses `ImageProcessor` (silhouette URL handling), declare the marker interface so the constructor's intersection type is satisfied:

```php
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use MagicSunday\Webtrees\ModuleBase\Contract\ModuleAssetUrlInterface;

class Module extends AbstractModule implements ModuleCustomInterface, ModuleAssetUrlInterface
{
    use ModuleCustomTrait;
    // ...
}
```

`ModuleCustomTrait` already provides the required `assetUrl()` method — only the interface declaration is new.

## What's inside

### `src/Processor/`
- **`DateProcessor`** — generation-aware date formatting (compact `getFormatted*` / `get*Full` API for tight chart labels; locale-aware legacy `getBirth*` / `getDeath*` / `getLifetimeDescription` API for everything else; marriage dates only via the compact API — `getFormattedMarriageDate()`, `getFormattedMarriageDateOfParents()`, `getMarriageDateFull()`)
- **`NameProcessor`** — name extraction from webtrees name HTML (DOM/XPath based — splits first/last/preferred, handles starredname, alternative and married names)
- **`ImageProcessor`** — highlight image + silhouette URL resolution
- **`PlaceProcessor`** — place name shortening (configurable parts) for chart labels

### `src/Model/`
- **`Symbols`** — backed enum for genealogical symbols (Birth ★, Death †, en-dash separator, MarriageDateUnknown sentinel)
- **`NameAbbreviation`** — backed enum + `resolve()` helper for the name-abbreviation strategy used in chart labels (auto / given-first / surname-first)
- **`PlaceStyle`** — enum for the way a place name is shortened (`Full`, `Levels`, `CityCountry`, `CityIso2`, `CityIso3`)
- **`PlaceFormatSpec`** — `final readonly` value object holding a fully resolved place-formatting instruction (style, level count, from-which-end)
- **`PlaceFormatChoice`** — backed enum for the place-detail options a module offers in its configuration; label-free, so the consuming module supplies its own translations

### `src/Support/`
- **`CompactDateFormat`** — derives a locale-aware, compact (numeric) date format string from a locale's CLDR/ICU short-date pattern, for `DateProcessor`'s compact API
- **`TextDirection`** — resolves script direction (LTR/RTL) for arbitrary strings
- **`Locale/IsoCountryMap`** — maps free-text country names from GEDCOM PLAC lines to ISO-3166-1 codes, built on `ext-intl`; used by `PlaceProcessor`'s country-resolving styles

### `src/Module/`
- **`VersionInformation`** — checks GitHub releases for newer module versions, with file cache. No chart module references it directly; it is instantiated by this library's own `Traits\ModuleCustomTrait::customModuleLatestVersion()` (which overrides webtrees' core method), and the webtrees control panel invokes that trait method

### `src/Traits/`
- **`ModuleCustomTrait`** — overrides webtrees' `customModuleLatestVersion()` to route through `VersionInformation`, and provides the `assetUrl()` helper the marker interface declares
- **`ModuleChartTrait`** — shared chart-module helpers for the consuming modules

### `src/Facade/`
- **`ModuleAwareDataFacadeTrait`** / **`RouteAwareDataFacadeTrait`** — traits a chart module's DataFacade uses to receive the module instance and route access

### `src/Contract/`
- **`ModuleAssetUrlInterface`** — marker interface that declares webtrees' `assetUrl()` helper so `ImageProcessor` can be type-narrowed without `method_exists` runtime checks

## Development

See [AGENTS.md](AGENTS.md) for the full development workflow, including the
`make link-base` sibling-clone pattern and the tooling-parity policy with
consumer modules.

Quick reference — this library has no module-local container; PHP runs
through the webtrees-docker buildbox:

```shell
cd /path/to/webtrees-docker && docker compose run --rm buildbox bash -c \
    'cd /var/webtrees/app/vendor/magicsunday/webtrees-module-base && composer ci:test'
```

Substitute `composer install`, `composer update`, `composer ci:cgl` or
`composer ci:rector` for the last command as needed.

## License

GPL-3.0-or-later — see [LICENSE](LICENSE).
