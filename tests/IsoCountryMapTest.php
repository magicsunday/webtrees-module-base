<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use MagicSunday\Webtrees\ModuleBase\Support\Locale\IsoCountryMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

use function restore_error_handler;
use function set_error_handler;

/**
 * Locks the {@see IsoCountryMap} contract: the apostrophe-and-diacritic
 * normalisation `resolve()` applies (ICU's display-region output uses the
 * curly U+2019 apostrophe in names like "Côte d'Ivoire", but GEDCOM authors can
 * stamp any of six common single-quote variants, and all six must fold to the
 * same ISO-2 code), the alpha-3 ↔ alpha-2 bridge (`resolve()` accepting an
 * alpha-3 token, `toAlpha3()` converting the other direction), both memoisation
 * caches, `label()`, and `clearCache()` resetting every one of them.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(IsoCountryMap::class)]
final class IsoCountryMapTest extends TestCase
{
    protected function setUp(): void
    {
        IsoCountryMap::clearCache();
    }

    /**
     * Several tests inject a sentinel into a private static cache via
     * {@see ReflectionProperty} to prove memoisation. A failing assertion in
     * one of those tests would otherwise leave the sentinel in the process-wide
     * static, leaking into every test class that runs afterwards — resetting
     * here, rather than at the end of the test body, guarantees the reset runs
     * even when the test itself fails.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        IsoCountryMap::clearCache();

        parent::tearDown();
    }

    /**
     * Every variant of "Côte d'Ivoire" with a different single- quote /
     * modifier-letter character at the apostrophe position must resolve to CI.
     * The six characters covered are:
     *
     *   U+0027 — APOSTROPHE (ASCII)
     *   U+2019 — RIGHT SINGLE QUOTATION MARK (ICU canonical)
     *   U+2018 — LEFT SINGLE QUOTATION MARK
     *   U+02BB — MODIFIER LETTER TURNED COMMA (Hawai'i)
     *   U+02BC — MODIFIER LETTER APOSTROPHE
     *   U+201B — SINGLE HIGH-REVERSED-9 QUOTATION MARK
     *
     * @return iterable<string, array{0: string}>
     */
    public static function apostropheVariants(): iterable
    {
        yield 'ASCII U+0027' => ["Côte d\u{0027}Ivoire"];
        yield 'curly U+2019' => ["Côte d\u{2019}Ivoire"];
        yield 'left U+2018' => ["Côte d\u{2018}Ivoire"];
        yield 'okina U+02BB' => ["Côte d\u{02BB}Ivoire"];
        yield 'modifier U+02BC' => ["Côte d\u{02BC}Ivoire"];
        yield 'high-9 U+201B' => ["Côte d\u{201B}Ivoire"];
    }

    /**
     * Every apostrophe/modifier-letter variant listed by {@see self::apostropheVariants()}
     * must fold to the same ISO-2 code, regardless of which single-quote
     * character the source data used at the apostrophe position.
     *
     * @param string $name Country name spelled with one apostrophe variant
     *
     * @return void
     */
    #[Test]
    #[DataProvider('apostropheVariants')]
    public function resolveFoldsEveryApostropheVariantToTheSameIso(string $name): void
    {
        self::assertSame('CI', (new IsoCountryMap())->resolve($name));
    }

    /**
     * Diacritics in the country name must round-trip without mojibake.
     * "Österreich" (German for Austria) → AT.
     */
    #[Test]
    public function resolveHandlesDiacriticsInLocalisedCountryName(): void
    {
        self::assertSame('AT', (new IsoCountryMap())->resolve('Österreich'));
    }

    /**
     * Manual aliases (USA, UK, Deutschland, …) must win over ICU's
     * display-region names so the resolver matches what GEDCOM authors actually
     * write rather than only ICU's canonical labels.
     */
    #[Test]
    public function resolveHonoursManualAliasOverIcuLabel(): void
    {
        $map = new IsoCountryMap();
        self::assertSame('US', $map->resolve('USA'));
        self::assertSame('US', $map->resolve('U.S.A'));
        self::assertSame('GB', $map->resolve('UK'));
        self::assertSame('DE', $map->resolve('Deutschland'));
    }

    /**
     * `resolve()` returns null for free-text country names that don't match any
     * locale-aware label or alias — "Atlantis" is not a real country and stays
     * unresolved.
     */
    #[Test]
    public function resolveReturnsNullForUnknownCountry(): void
    {
        self::assertNull((new IsoCountryMap())->resolve('Atlantis'));
    }

    /**
     * ISO-3166-1 alpha-3 country codes ("DEU", "FRA", "GBR") that GEDCOM
     * exporters stamp into the country segment must resolve to their alpha-2
     * sibling. ICU canonicalises the alpha-3 region subtag onto the same display
     * name as the alpha-2 code, so the resolver bridges through the existing
     * name → ISO-2 map that backs `resolve()`, instead of reversing the
     * separate alpha-2 → alpha-3 table `toAlpha3()` uses.
     *
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function alpha3Codes(): iterable
    {
        yield 'Germany DEU' => ['DEU', 'DE'];
        yield 'France FRA' => ['FRA', 'FR'];
        yield 'United Kingdom GBR' => ['GBR', 'GB'];
        yield 'Switzerland CHE' => ['CHE', 'CH'];
        yield 'Austria AUT' => ['AUT', 'AT'];
        yield 'Côte d’Ivoire CIV' => ['CIV', 'CI'];
        yield 'lower-case deu' => ['deu', 'DE'];
        yield 'whitespace-padded fra' => [' FRA ', 'FR'];
    }

    /**
     * @param string $alpha3 Raw alpha-3 country segment
     * @param string $iso2   Expected ISO-3166-1 alpha-2 code
     */
    #[Test]
    #[DataProvider('alpha3Codes')]
    public function resolveAcceptsIsoAlpha3CountryCodes(string $alpha3, string $iso2): void
    {
        self::assertSame($iso2, (new IsoCountryMap())->resolve($alpha3));
    }

    /**
     * The reporter's exact place string (issue #208) — a four-segment hierarchy
     * whose country tail is the alpha-3 code "DEU" — must resolve to Germany via
     * `resolveFromPlace()`, which peels the trailing comma segment before the
     * lookup.
     */
    #[Test]
    public function resolveFromPlaceAcceptsAlpha3CountryTail(): void
    {
        self::assertSame(
            'DE',
            (new IsoCountryMap())->resolveFromPlace('Freiburg, Freiburg, Baden-Württemberg, DEU'),
        );
    }

    /**
     * `resolveFromPlace()`'s comma-less branch: a place string with no comma at
     * all is resolved as-is, without splitting off a trailing segment. Every
     * other `resolveFromPlace()` test in this file passes a comma-separated
     * string, so this branch was otherwise never exercised.
     *
     * @return void
     */
    #[Test]
    public function resolveFromPlaceAcceptsAPlaceWithNoComma(): void
    {
        self::assertSame('DE', (new IsoCountryMap())->resolveFromPlace('Deutschland'));
    }

    /**
     * `resolve()`'s empty-normalised-name guard is reachable through
     * `resolveFromPlace()` on realistic GEDCOM artefacts: a trailing comma
     * leaves an empty country segment, and a punctuation-only segment
     * `normalise()` trims down to the empty string.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function placesWithAnEmptyCountrySegment(): iterable
    {
        yield 'a trailing comma leaves an empty segment' => ['Berlin,'];
        yield 'a punctuation-only segment normalises to empty' => ['Berlin, ...'];
    }

    /**
     * @param string $place Place string whose country segment normalises to the empty string
     *
     * @return void
     */
    #[Test]
    #[DataProvider('placesWithAnEmptyCountrySegment')]
    public function resolveFromPlaceReturnsNullForAnEmptyCountrySegment(string $place): void
    {
        self::assertNull((new IsoCountryMap())->resolveFromPlace($place));
    }

    /**
     * A three-letter token that is not a valid ISO-3166-1 alpha-3 code must stay
     * unresolved — ICU echoes an unknown region subtag back unchanged, and the
     * resolver must treat that echo as "no match" rather than as a hit.
     */
    #[Test]
    public function resolveReturnsNullForUnknownThreeLetterToken(): void
    {
        self::assertNull((new IsoCountryMap())->resolve('XYZ'));
    }

    /**
     * The alpha-3 ICU bridge memoises its per-token result in
     * {@see IsoCountryMap::$alpha3Cache}. Asserting the same value twice, as a
     * repeated lookup would, passes even with no memoisation at all — a fresh
     * ICU lookup for "DEU" also yields "DE". The primed cache entry is instead
     * replaced with a sentinel via reflection: a re-read of ICU would still
     * yield "DE", so getting the sentinel back proves the cache entry, not
     * ICU, was read.
     *
     * The null branch needs its own proof: {@see IsoCountryMap::resolveAlpha3()}
     * distinguishes "not yet cached" from "cached as unresolved" with
     * `array_key_exists()` rather than `??`, so "GBR" — a code ICU would
     * happily resolve to "GB" — is forced into the cache as null. If the read
     * ever degraded to treating a cached null as a miss, the live ICU lookup
     * would win and "GB" would come back instead of null.
     *
     * @return void
     */
    #[Test]
    public function resolveReadsTheMemoisedAlpha3Result(): void
    {
        $map = new IsoCountryMap();

        self::assertSame('DE', $map->resolve('DEU'));

        $property = new ReflectionProperty(IsoCountryMap::class, 'alpha3Cache');
        $property->setValue(null, ['deu' => 'SENTINEL', 'gbr' => null]);

        self::assertSame('SENTINEL', $map->resolve('DEU'));
        self::assertNull($map->resolve('GBR'));
    }

    /**
     * The UK home-nation codes the webtrees core counts as countries
     * (CountryService::iso3166(): ENG / SCT / WLS / NIR) must fold onto GB. They
     * are Chapman / GEDCOM subdivision codes, not ISO-3166-1 alpha-3, so ICU
     * cannot resolve them — the manual alias carries them.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function ukHomeNationCodes(): iterable
    {
        yield 'England ENG' => ['ENG'];
        yield 'Scotland SCT' => ['SCT'];
        yield 'Wales WLS' => ['WLS'];
        yield 'Northern Ireland NIR' => ['NIR'];
    }

    /**
     * @param string $code UK home-nation subdivision code
     */
    #[Test]
    #[DataProvider('ukHomeNationCodes')]
    public function resolveFoldsUkHomeNationCodesOntoGb(string $code): void
    {
        self::assertSame('GB', (new IsoCountryMap())->resolve($code));
    }

    /**
     * `label()` returns the active webtrees locale's name for a given ISO code.
     * With no I18N bootstrap (the test runs outside the webtrees request
     * lifecycle), the resolver falls back to en_US.
     */
    #[Test]
    public function labelReturnsEnglishNameWhenNoLocaleIsActive(): void
    {
        self::assertSame('Germany', (new IsoCountryMap())->label('DE'));
    }

    /**
     * `label()` echoes the ISO code unchanged when ICU has no display-region
     * name for the input. Protects the caller from ever seeing an empty label
     * string.
     */
    #[Test]
    public function labelEchoesUnknownIsoCodeUnchanged(): void
    {
        self::assertSame('XX', (new IsoCountryMap())->label('XX'));
    }

    /**
     * ISO-3166-1 alpha-2 codes convert to their alpha-3 siblings through ICU's
     * supplemental code mappings.
     *
     * @param string      $iso2     Alpha-2 country code fed into toAlpha3()
     * @param string|null $expected Expected alpha-3 code, or null when ICU has none
     *
     * @return void
     */
    #[Test]
    #[DataProvider('alpha3Provider')]
    public function alphaTwoCodesConvertToAlphaThree(string $iso2, ?string $expected): void
    {
        self::assertSame($expected, (new IsoCountryMap('en_US'))->toAlpha3($iso2));
    }

    /**
     * Only codes this class recognises resolve. ICU's own table is wider (303
     * rows against our 249) and includes withdrawn codes and CLDR-internal
     * pseudo-regions, so it is intersected with ISO2_CODES — otherwise "EU"
     * would yield "QUU", which is not an ISO-3166-1 code at all.
     *
     * The positive expectations come from the live ICU table and can shift with
     * an ICU upgrade; the null rows are contract and must hold regardless.
     *
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function alpha3Provider(): array
    {
        return [
            'Germany'                  => ['DE', 'DEU'],
            'France'                   => ['FR', 'FRA'],
            'United Kingdom'           => ['GB', 'GBR'],
            'United States'            => ['US', 'USA'],
            'non-ASCII country name'   => ['CI', 'CIV'],
            'outlying territory'       => ['AX', 'ALA'],
            'lower case input'         => ['de', 'DEU'],
            'surrounding whitespace'   => [' de ', 'DEU'],
            'CLDR user-assigned range' => ['ZZ', null],
            'withdrawn code'           => ['SU', null],
            'CLDR-internal region'     => ['EU', null],
            'unassigned code'          => ['AB', null],
            'digit in the code'        => ['D1', null],
            'three letters in'         => ['DEU', null],
            'empty input'              => ['', null],
        ];
    }

    /**
     * The second lookup reads the materialised table instead of going back to
     * ICU. Asserting the same value twice would pass without any memoisation at
     * all, so the primed table is replaced with a sentinel: a re-read of ICU
     * would still yield DEU.
     *
     * This also pins the docblock's claim that the table is shared across
     * instances.
     *
     * @return void
     */
    #[Test]
    public function alphaThreeLookupsReadTheMemoisedTable(): void
    {
        $map = new IsoCountryMap('en_US');

        self::assertSame('DEU', $map->toAlpha3('DE'));

        $property = new ReflectionProperty(IsoCountryMap::class, 'alpha2ToAlpha3Map');
        $property->setValue(null, ['DE' => 'SENTINEL']);

        self::assertSame('SENTINEL', $map->toAlpha3('DE'));
        self::assertSame('SENTINEL', (new IsoCountryMap('de_DE'))->toAlpha3('DE'));
    }

    /**
     * `alpha2ToAlpha3Map()` installs a scoped error handler for the duration of
     * the ICU probe and must restore the caller's handler before returning. If
     * that restoration were ever dropped, every later test in this shared
     * process would silently lose whatever handler was active before this call
     * — e.g. PHPUnit's `failOnWarning` handler. A sentinel closure installed
     * before the call, and read back via `set_error_handler(null)` afterwards,
     * proves the caller's handler — not the probe's own — is what remains. This
     * only proves the end state after the call is correct; it cannot tell a
     * restore placed in a `finally` block from one placed at the end of the
     * `try` body.
     *
     * @return void
     */
    #[Test]
    public function toAlpha3RestoresTheCallersErrorHandler(): void
    {
        $sentinel = static fn (): bool => false;
        set_error_handler($sentinel);

        try {
            (new IsoCountryMap('en_US'))->toAlpha3('DE');

            self::assertSame($sentinel, set_error_handler(null), 'the ICU probe must restore the caller handler');
        } finally {
            // Undoes both set_error_handler() calls above (the sentinel and,
            // if the assertion reached it, the null probe read) regardless of
            // whether the assertion passed — a failure here is exactly the
            // regression this test guards, and must not leave either handler
            // on the process-wide stack for later tests.
            restore_error_handler();
            restore_error_handler();
        }
    }

    /**
     * clearCache() must leave no primed static state behind — including caches
     * added after this test was written. Comparing the whole static-property set
     * against a pristine snapshot keeps the guarantee general instead of naming
     * one field.
     *
     * @return void
     */
    #[Test]
    public function clearCacheLeavesNoPrimedStaticState(): void
    {
        IsoCountryMap::clearCache();

        $pristine = (new ReflectionClass(IsoCountryMap::class))->getStaticProperties();

        $map = new IsoCountryMap('en_US');
        $map->resolve('DEU');
        $map->toAlpha3('DE');
        $map->label('DE');
        $map->resolveFromPlace('Berlin, DE');

        self::assertNotSame(
            $pristine,
            (new ReflectionClass(IsoCountryMap::class))->getStaticProperties(),
            'the fixture must actually prime the caches, or this test is vacuous'
        );

        IsoCountryMap::clearCache();

        self::assertSame(
            $pristine,
            (new ReflectionClass(IsoCountryMap::class))->getStaticProperties(),
            'clearCache() must reset every static cache, including ones added later'
        );
    }
}
