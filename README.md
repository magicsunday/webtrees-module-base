[![Latest version](https://img.shields.io/github/v/release/magicsunday/webtrees-module-base?sort=semver)](https://github.com/magicsunday/webtrees-module-base/releases/latest)
[![License](https://img.shields.io/github/license/magicsunday/webtrees-module-base)](https://github.com/magicsunday/webtrees-module-base/blob/main/LICENSE)
[![CI](https://github.com/magicsunday/webtrees-module-base/actions/workflows/ci.yml/badge.svg)](https://github.com/magicsunday/webtrees-module-base/actions/workflows/ci.yml)

# webtrees-module-base

Shared PHP base classes for the [magicsunday](https://github.com/magicsunday) family of [webtrees](https://www.webtrees.net) chart modules. Centralises the name, image and date processing logic, common models, and module helpers (GitHub release-version checking with file cache) so the chart modules do not have to reimplement the shared pieces.

This package ships no UI of its own — it is consumed as a Composer dependency by:

- [webtrees-fan-chart](https://github.com/magicsunday/webtrees-fan-chart) — SVG ancestor fan chart
- [webtrees-pedigree-chart](https://github.com/magicsunday/webtrees-pedigree-chart) — SVG pedigree chart
- [webtrees-descendants-chart](https://github.com/magicsunday/webtrees-descendants-chart) — SVG descendants chart
- [webtrees-statistics](https://github.com/magicsunday/webtrees-statistics) — genealogy statistics dashboard (consumes only the shared core plus `IsoCountryMap`)

> **Scope note:** not every base component is consumed by every module. The statistics module uses only the shared core plus `IsoCountryMap`, and the chart modules adopt the place, compact date and fact components as they need them. A component can live in the base before its second consumer exists, so that the consumer can adopt it without a namespace move. To see which module uses a class, search the sibling checkouts, for example `grep -rl 'ModuleBase.Processor.PlaceProcessor' ../webtrees-*/src | grep -v '^../webtrees-module-base/'`.

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
- **`DateProcessor`** — generation-aware date formatting (compact `getFormatted*` / `get*Full` API for tight chart labels; locale-aware legacy `getBirth*` / `getDeath*` / `getLifetimeDescription` API for everything else; marriage dates only via the compact API: `getFormattedMarriageDate()`, `getFormattedMarriageDateOfParents()`, `getMarriageDateFull()`). The static `formatFactDate()` gives the text of the date of any fact: compact for a complete date, the webtrees wording for a partial, qualified or ranged one
- **`NameProcessor`** — name extraction from webtrees name HTML (DOM/XPath based — splits first/last/preferred, handles starredname, alternative and married names). `getNameSegments()` returns the name as ordered, drawable `NameSegment` objects
- **`ImageProcessor`** — highlight image + silhouette URL resolution
- **`PlaceProcessor`** — place name shortening (configurable parts) for chart labels
- **`FactResolver`**: resolves the facts a chart box shows, one slot per row: the first existing birth equivalent, the first existing death equivalent, then the optional tags of the tree preference "Other facts to show in charts". Returns `FactView` objects, `null` for a missing fact

### `src/Model/`
- **`Symbols`** — backed enum for genealogical symbols (Birth ★, Death †, en-dash separator, MarriageDateUnknown sentinel)
- **`NameAbbreviation`** — backed enum + `resolve()` helper for the name-abbreviation strategy used in chart labels (auto / given-first / surname-first)
- **`PlaceStyle`** — enum for the way a place name is shortened (`Full`, `Levels`, `CityCountry`, `CityIso2`, `CityIso3`)
- **`PlaceFormatSpec`** — `final readonly` value object holding a fully resolved place-formatting instruction (style, level count, from-which-end)
- **`PlaceFormatChoice`** — backed enum for the place-detail options a module offers in its configuration; label-free, so the consuming module supplies its own translations
- **`NodeData`** — the person DTO of the linked tree charts (pedigree and descendants), serialised to JSON for the front end. An optional `facts` list, one `FactView` or `null` per row, is appended to the payload only when it is not empty
- **`FactView`**: `final readonly` value object for one fact of a chart box (tag, label, date, place, full place, value), all plain text
- **`NameSegment`** / **`NameSegmentKind`** — `final readonly` value object and backed enum for one drawable part of a name (given, surname, nickname, married)

### `src/Support/`
- **`CompactDateFormat`** — derives a locale-aware, compact (numeric) date format string from a locale's CLDR/ICU short-date pattern, for `DateProcessor`'s compact API
- **`TextDirection`** — resolves script direction (LTR/RTL) for arbitrary strings
- **`PlainText`**: reduces webtrees markup to plain text (strips the tags, decodes the entities)
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

### `src/Catalogue/`
- **`PunctuationResolver`**, **`CatalogueToolchain`** and **`FuzzyResolverCommand`** clear the fuzzy entries of a module's translation catalogues whose source text only changed in punctuation or spacing. `msgmerge` pairs the old translation with a slightly changed source text and marks it fuzzy, and `msgfmt` leaves fuzzy entries out of the compiled catalogue unless it is told to use them (see the gettext manual on [fuzzy entries](https://www.gnu.org/software/gettext/manual/html_node/Fuzzy-Entries.html) and on [`msgfmt`](https://www.gnu.org/software/gettext/manual/html_node/msgfmt-Invocation.html)). Every other fuzzy entry stays fuzzy and is counted and, up to a limit per catalogue, listed for a person to judge. An entry with a context is listed with the context and a bar in front of its source text. A translation whose separator was swapped is listed as well, behind a tilde, because the swap reads the order of the separators and cannot prove that a separator of the translation stands for the same place as one of the source text. The gettext tools write the catalogue, so its layout stays the one `msgmerge` produces. Run it from the language pipeline of a module between the merge and the compilation, with the binary Composer installs for this package, shown here under the default bin directory:

```
vendor/bin/resolve-fuzzy resources/lang/messages.pot resources/lang/*/messages.po
```

  It needs `msgcat`, `msgattrib` and `msgmerge` on the path. `Resolution` is the value object the resolver returns, `ParsedEntry` and `ResolvedEntry` carry the entries inside it, `PrintableText` filters control characters out of the report and `CatalogueToolException` is what a failing tool raises. The command reads every catalogue through `msgcat --no-wrap` first, so the resolver sees one canonical layout whatever layout the file had. A catalogue that gettext cannot read is named with a line of its own and left as it is, and so is one whose layout the resolver does not expect afterwards (`CatalogueLayoutException`), so run `msgmerge` over it first. A catalogue that cannot be rewritten, because its directory is not writable or a tool fails on it, is named the same way while the others go on. A catalogue that is missing, not readable or a link is named with a line of its own and left as it is, because the new file would replace a link, so name the file it points to. A rewritten catalogue keeps its permissions, and its owner and group where the process may set them. A comment block after the last entry belongs to no entry and is not kept when a catalogue is rewritten. Before a catalogue is read, it is merged once more from the template in a scratch copy, and a catalogue that this merge would change in any byte is refused the same way. To bring it into that layout, write it once with `msgmerge --previous --output-file=<new file> <catalogue> <template>` and replace the catalogue with the new file, because `msgmerge --update` leaves a file alone that it reads to the same entries. A catalogue that an older gettext wrapped differently, in long lines with a web address for example, is refused the same way until it is written once again. That covers a catalogue written by another tool or by hand, one laid out with other merge options, one merged from another template, and one with text the tools would cut or join when they read it, a NUL byte or a carriage return for example. The command exits with 1 as soon as one catalogue is refused or declined, and with 0 when every catalogue was processed, whatever stays fuzzy. When a separator of the source text was swapped and the translation holds a mark of the separator family with a space other than the plain one next to it (a no-break space for example), the entry stays fuzzy. Catalogues that are not UTF-8 and catalogues of tens of megabytes are outside what the command is built for, and nothing guards against them beyond the refusals above. The template has to be the one the catalogue was merged from. When it holds other entries, because a source text changed again or an entry is missing, or when the last merge would change or lose the translation of an entry or mark one fuzzy, the run names that catalogue, leaves it as it is and fails.

- **`WriteTranslationsCommand`** writes the translations a module keeps in one JSON file into its catalogues, with the right number of plural forms for each locale. The file maps a source text to its translation per locale, a plural text lists one string per form of the plural rule of the locale (a key with a context is written as `context` and `msgid` joined by U+0004):

  ```json
  {
      "Hello": {"de": "Hallo", "ru": "Привет"},
      "%s item": {"de": ["%s Ding", "%s Dinge"], "ru": ["%s вещь", "%s вещи", "%s вещей"]}
  }
  ```

  ```bash
  vendor/bin/write-translations --core-owned=dev/core-owned.pot dev/translations.json resources/lang/messages.pot resources/lang/*/messages.po
  ```

  Only an entry that has no translation yet is written. An entry that is fuzzy, that is already translated, that webtrees core translates itself (the optional `--core-owned` template) or that the file holds nothing for is left alone. A plural entry whose strings do not match the form count of the locale is refused and named, and the run exits with 1. The locale is the name of the directory of the catalogue and the number of forms comes from the plural rule of that locale. A catalogue whose header declares another number of plural forms than the locale has is refused and named before anything is written. So is a catalogue whose header declares none, when a plural entry would be written to it. The run exits with 1 when anything was refused or a file it needs is unusable, with 2 when the arguments are too few, and otherwise with 0. The catalogues have to be what a merge with the template writes, as for `resolve-fuzzy`, and a second run changes nothing.

### `src/Testing/`
- **`AbstractCatalogueStructureTestCase`**, a PHPUnit test case that checks the translation catalogues a module ships. Extend it in the module's `tests/` and return the catalogue directory from `languageDirectory()`:

```php
use MagicSunday\Webtrees\ModuleBase\Testing\AbstractCatalogueStructureTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class CatalogueStructureTest extends AbstractCatalogueStructureTestCase
{
    protected static function languageDirectory(): string
    {
        return __DIR__ . '/../resources/lang';
    }
}
```

  webtrees merges a module's catalogue over the catalogue of webtrees itself, so a plural entry with the wrong number of forms or an empty form damages the translation of the same text everywhere in the installation. The checks guard against that. PHPUnit is a dev dependency of the consuming module. The subclass carries `#[CoversNothing]` because it covers no class. A `phpunit.xml` with `requireCoverageMetadata="true"` demands the attribute. A module whose catalogues carry no plural string overrides `shipsPluralEntries()` and returns `false`. The case then checks that no plural entry exists, so one added later fails until the override is removed.

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
