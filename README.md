[![Latest version](https://img.shields.io/github/v/release/magicsunday/webtrees-module-base?sort=semver)](https://github.com/magicsunday/webtrees-module-base/releases/latest)
[![License](https://img.shields.io/github/license/magicsunday/webtrees-module-base)](https://github.com/magicsunday/webtrees-module-base/blob/main/LICENSE)
[![CI](https://github.com/magicsunday/webtrees-module-base/actions/workflows/ci.yml/badge.svg)](https://github.com/magicsunday/webtrees-module-base/actions/workflows/ci.yml)

# webtrees-module-base

Shared PHP base classes for the [magicsunday](https://github.com/magicsunday) family of [webtrees](https://www.webtrees.net) chart modules. Centralises the date, name, image and place processing logic, common models (genealogical symbols enum, tree node), and module helpers (GitHub release-version checking with file cache) so each chart module does not have to reimplement them.

This package ships no UI of its own — it is consumed as a Composer dependency by:

- [webtrees-fan-chart](https://github.com/magicsunday/webtrees-fan-chart) — SVG ancestor fan chart
- [webtrees-pedigree-chart](https://github.com/magicsunday/webtrees-pedigree-chart) — SVG pedigree chart
- [webtrees-descendants-chart](https://github.com/magicsunday/webtrees-descendants-chart) — SVG descendants chart

## Requirements

- PHP 8.3 - 8.5 with extension `dom`
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
- **`DateProcessor`** — generation-aware date formatting (compact `getFormatted*` API for tight chart labels; locale-aware legacy `getBirth*` / `getDeath*` / `getMarriage*` API for everything else)
- **`NameProcessor`** — name extraction from webtrees name HTML (DOM/XPath based — splits first/last/preferred, handles starredname, alternative and married names)
- **`ImageProcessor`** — highlight image + silhouette URL resolution
- **`PlaceProcessor`** — place name shortening (configurable parts) for chart labels

### `src/Model/`
- **`Symbols`** — backed enum for genealogical symbols (Birth ★, Death †, MARRIAGE_DATE_UNKNOWN sentinel, …)
- **`Node`, `NodeData`** — tree-node value objects with D3-friendly JSON serialisation, used by chart modules' `DataFacade`

### `src/Module/`
- **`VersionInformation`** — checks GitHub releases for newer module versions, with file cache (used by the chart modules' admin pages)

### `src/Contract/`
- **`ModuleAssetUrlInterface`** — marker interface that declares webtrees' `assetUrl()` helper so `ImageProcessor` can be type-narrowed without `method_exists` runtime checks

## Development

See [AGENTS.md](AGENTS.md) for the full development workflow including the buildbox vs standalone modes, the `make link-base` sibling-clone pattern, and the tooling-parity policy with consumer modules.

Quick reference:

```shell
# Standalone clone (compose.yaml provides PHP 8 + composer)
make install
make ci-test            # phplint + phpstan + rector + phpunit + cgl
make ci-cgl             # auto-fix php-cs-fixer
make ci-rector          # auto-fix rector

# Inside the parent webtrees buildbox
composer ci:test
composer ci:cgl
composer ci:rector
```

## License

GPL-3.0-or-later — see [LICENSE](LICENSE).
