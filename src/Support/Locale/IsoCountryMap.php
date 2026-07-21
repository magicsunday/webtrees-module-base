<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Support\Locale;

use Fisharebest\Webtrees\I18N;
use Locale;
use ResourceBundle;
use Throwable;

use function array_key_exists;
use function array_unique;
use function in_array;
use function is_string;
use function iterator_to_array;
use function mb_strtolower;
use function preg_match;
use function preg_replace;
use function restore_error_handler;
use function set_error_handler;
use function str_replace;
use function strrpos;
use function strtolower;
use function strtoupper;
use function substr;
use function trim;

/**
 * Maps free-text country names from GEDCOM PLAC lines to ISO-3166-1 alpha-2
 * codes (e.g. "Germany" → "DE", "Deutschland" → "DE"), and separately converts
 * between alpha-2 and alpha-3 codes via ICU's supplemental `codeMappings`
 * table (e.g. "DE" → "DEU").
 *
 * Built on PHP's intl extension (Locale::getDisplayRegion) which provides the
 * localised country-name dictionary that ships with ICU. The resolver tries a
 * small set of widely-used locales (English, German, French, Spanish, Italian,
 * Dutch, Portuguese, Polish, Russian) plus the user's current webtrees locale,
 * then caches the inverse lookup in a static map so repeat queries remain O(1).
 *
 * ISO-3166-1 alpha-3 codes ("DEU", "FRA") that GEDCOM exporters stamp into the
 * country segment are resolved by bridging through ICU: an alpha-3 region
 * subtag canonicalises onto the same display name as its alpha-2 sibling
 * (`-DEU` and `-DE` both yield "Germany"), so the alpha-3 code reuses the name
 * lookup. The UK home-nation codes ("ENG", "SCT", "WLS", "NIR") are not
 * ISO-3166-1, so they are carried as manual aliases.
 *
 * The core ships a country dictionary in
 * `Fisharebest\Webtrees\Statistics\Service\CountryService`, but it is marked
 * deprecated and slated for removal in webtrees 2.3 — coupling to it would
 * break the module, so the resolver stays self-contained.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class IsoCountryMap
{
    /**
     * Prefix that turns a bare region subtag into a well-formed BCP 47 tag for
     * {@see Locale::getDisplayRegion()}. "und" is the ISO-639-2 code for an
     * undetermined language, which is how the standard expresses "region only".
     * A bare leading hyphen ("-DE") happens to parse in current ICU builds but
     * is ill-formed, so it is not relied on.
     */
    private const string REGION_TAG_PREFIX = 'und-';

    /**
     * Locales whose ICU display-region names get folded into the reverse lookup
     * map. Covers the major Latin-script languages used in published genealogy
     * software.
     *
     * @var list<string>
     */
    private const array PRESEED_LOCALES = [
        'en_US',
        'de_DE',
        'fr_FR',
        'es_ES',
        'it_IT',
        'nl_NL',
        'pt_PT',
        'pl_PL',
        'ru_RU',
    ];

    /**
     * ISO-3166-1 alpha-2 country codes. Sourced from the published ISO
     * standard; superseded codes (e.g. "SU" for the Soviet Union) are
     * intentionally excluded — consumers render present-day territories, and a
     * place naming a historical polity keeps its plain-text spelling instead.
     *
     * @var list<string>
     */
    private const array ISO2_CODES = [
        'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR',
        'AS', 'AT', 'AU', 'AW', 'AX', 'AZ', 'BA', 'BB', 'BD', 'BE',
        'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ',
        'BR', 'BS', 'BT', 'BV', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD',
        'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR',
        'CU', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM',
        'DO', 'DZ', 'EC', 'EE', 'EG', 'EH', 'ER', 'ES', 'ET', 'FI',
        'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB', 'GD', 'GE', 'GF',
        'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS',
        'GT', 'GU', 'GW', 'GY', 'HK', 'HM', 'HN', 'HR', 'HT', 'HU',
        'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT',
        'JE', 'JM', 'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN',
        'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC', 'LI', 'LK',
        'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME',
        'MF', 'MG', 'MH', 'MK', 'ML', 'MM', 'MN', 'MO', 'MP', 'MQ',
        'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA',
        'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU',
        'NZ', 'OM', 'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM',
        'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS',
        'RU', 'RW', 'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI',
        'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS', 'ST', 'SV',
        'SX', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK',
        'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA',
        'UG', 'UM', 'US', 'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI',
        'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'ZA', 'ZM', 'ZW',
    ];

    /**
     * Common abbreviations and informal names that GEDCOM authors stamp into
     * PLAC fields. Mapped to their ISO-3166-1 alpha-2 codes so the resolver
     * catches them even though ICU's display-region names don't include these
     * forms.
     *
     * @var array<string, string>
     */
    private const array MANUAL_ALIASES = [
        'usa'                      => 'US',
        'u.s.a'                    => 'US',
        'u s a'                    => 'US',
        'united states of america' => 'US',
        'us'                       => 'US',
        'uk'                       => 'GB',
        'u.k'                      => 'GB',
        'great britain'            => 'GB',
        'england'                  => 'GB',
        'scotland'                 => 'GB',
        'wales'                    => 'GB',
        'northern ireland'         => 'GB',
        // UK home-nation codes the webtrees core treats as countries.
        // These are Chapman / GEDCOM subdivision codes, not ISO-3166-1
        // alpha-3, so ICU cannot resolve them — they fold onto GB here.
        'eng'            => 'GB',
        'sct'            => 'GB',
        'wls'            => 'GB',
        'nir'            => 'GB',
        'uae'            => 'AE',
        'ussr'           => 'RU',
        'czechoslovakia' => 'CZ',
        'deutschland'    => 'DE',
        'österreich'     => 'AT',
        'oesterreich'    => 'AT',
        'schweiz'        => 'CH',
    ];

    /**
     * Cached reverse lookup: normalised country name → ISO-2 code. Populated
     * lazily on first resolve() call and shared across every instance.
     *
     * @var array<string, string>|null
     */
    private static ?array $reverseLookup = null;

    /**
     * Memoised results of the alpha-3 ICU bridge, keyed by normalised token →
     * resolved ISO-2 code (or `null` for a token the bridge cannot resolve).
     * {@see self::resolveAlpha3()} calls into ICU, so without this a whole-tree scan
     * of individuals recorded in one country (e.g. every PLAC ending in "DEU")
     * would repeat the same `getDisplayRegion()` lookup for every record. The
     * bridge fixes the en_US locale, so the result is locale-independent and the
     * cache is safely shared across instances like {@see self::$reverseLookup}.
     *
     * @var array<string, string|null>
     */
    private static array $alpha3Cache = [];

    /**
     * ICU's alpha-2 → alpha-3 table, built lazily on first use. Null until the
     * ICU bundle has been read; an empty array means the ICU data was
     * unavailable and every lookup degrades to "unknown" from then on. The table
     * is locale-independent, so it is safely shared across instances like
     * {@see self::$reverseLookup}.
     *
     * @var array<string, string>|null
     */
    private static ?array $alpha2ToAlpha3Map = null;

    /**
     * @param string $userLocale Optional extra locale folded into the reverse lookup. Empty string defaults to the
     *                           active webtrees I18N tag — pass an explicit value only when overriding for tests or
     *                           for a non-user-facing label resolution.
     */
    public function __construct(
        private readonly string $userLocale = '',
    ) {
    }

    /**
     * The effective locale for label resolution: the explicit `$userLocale` if
     * non-empty, otherwise the active webtrees locale (so labels match the rest
     * of the UI's language). Falls back to `en_US` when no webtrees locale is
     * active — this happens in unit tests that don't bootstrap I18N.
     */
    private function effectiveLocale(): string
    {
        if ($this->userLocale !== '') {
            return $this->userLocale;
        }

        try {
            $tag = I18N::languageTag();
        } catch (Throwable) {
            return 'en_US';
        }

        return $tag !== '' ? $tag : 'en_US';
    }

    /**
     * Whether $value has the shape of an ISO-3166-1 alpha-2 country code: exactly
     * two letters, case-insensitive. Purely a shape test — it does not check
     * whether the code is actually recognised, callers combine this with a lookup
     * for that. Shared with
     * {@see \MagicSunday\Webtrees\ModuleBase\Processor\PlaceProcessor} so the case
     * tolerance cannot drift between the two consumers.
     *
     * @param string $value Candidate token
     */
    public static function isAlpha2Shape(string $value): bool
    {
        return preg_match('/^[A-Za-z]{2}$/', $value) === 1;
    }

    /**
     * Whether $value has the shape of an ISO-3166-1 alpha-3 country code:
     * exactly three letters, case-insensitive. Purely a shape test — it does
     * not check whether the code is actually recognised, callers combine this
     * with a lookup for that. Shared with
     * {@see \MagicSunday\Webtrees\ModuleBase\Processor\PlaceProcessor} so the
     * case tolerance cannot drift between the two consumers.
     *
     * @param string $value Candidate token
     */
    public static function isAlpha3Shape(string $value): bool
    {
        return preg_match('/^[A-Za-z]{3}$/', $value) === 1;
    }

    /**
     * Resolve a free-text country name to its ISO-3166-1 alpha-2 code. Returns
     * null when the name doesn't match any known country for any of the
     * pre-seeded locales.
     *
     * @param string $name Raw place segment (e.g. "Germany", "Deutschland", "Allemagne")
     */
    public function resolve(string $name): ?string
    {
        $normalised = $this->normalise($name);

        if ($normalised === '') {
            return null;
        }

        $map = $this->lookupMap();

        return $map[$normalised] ?? $this->resolveAlpha3($normalised, $map);
    }

    /**
     * Resolve an ISO-3166-1 alpha-3 country code ("DEU", "FRA", "GBR") to its
     * alpha-2 sibling. ICU canonicalises an alpha-3 region subtag onto the same
     * display name as the alpha-2 code (`-DEU` and `-DE` both yield "Germany"),
     * so the alpha-3 code is bridged through the existing name → ISO-2 map that
     * backs {@see self::resolve()}, rather than through
     * {@see self::alpha2ToAlpha3Map()} — that table runs the opposite direction
     * (alpha-2 → alpha-3, for {@see self::toAlpha3()}), and reusing the
     * display-name lookup this method already shares with the primary resolver
     * is shorter than inverting a second table. Returns null for any token ICU
     * does not recognise as a region — it echoes an unknown subtag back
     * unchanged, which is treated as "no match". The per-token result (hit or
     * null) is memoised in
     * {@see self::$alpha3Cache} so a whole-tree scan resolves each distinct code
     * through ICU only once.
     *
     * @param string                $normalised Already lower-cased, whitespace-collapsed candidate
     * @param array<string, string> $map        The reverse name → ISO-2 lookup
     */
    private function resolveAlpha3(string $normalised, array $map): ?string
    {
        if (array_key_exists($normalised, self::$alpha3Cache)) {
            return self::$alpha3Cache[$normalised];
        }

        $resolved = null;

        if (self::isAlpha3Shape($normalised)) {
            // Bridge through a fixed locale so the canonical display name
            // matches the en_US key the reverse map is always seeded with
            // first. ICU echoes an unknown subtag back unchanged (uppercased),
            // so an echo equal to the token means "no region".
            $name = (string) Locale::getDisplayRegion(self::REGION_TAG_PREFIX . strtoupper($normalised), 'en_US');

            // mb_strtolower(), not strtolower(): the display name can contain
            // non-ASCII letters (a resolved region name like "Åland Islands",
            // though the echoed-back subtag is always ASCII) that strtolower()
            // leaves unfolded, which would then mismatch $normalised — already
            // folded via mb_strtolower() inside self::normalise().
            if (($name !== '') && (mb_strtolower($name, 'UTF-8') !== $normalised)) {
                $resolved = $map[$this->normalise($name)] ?? null;
            }
        }

        return self::$alpha3Cache[$normalised] = $resolved;
    }

    /**
     * Resolve the country segment of a full GEDCOM PLAC string to its
     * ISO-3166-1 alpha-2 code. The country is the last comma-separated segment
     * by GEDCOM convention ("Hamburg, Germany" → "Germany"); a string without a
     * comma is resolved as-is. Returns null when the segment matches no known
     * country.
     *
     * @param string $place A full GEDCOM PLAC string
     */
    public function resolveFromPlace(string $place): ?string
    {
        $trimmed = trim($place);
        $comma   = strrpos($trimmed, ',');
        $segment = $comma === false ? $trimmed : trim(substr($trimmed, $comma + 1));

        return $this->resolve($segment);
    }

    /**
     * Localised display name for a given ISO-3166-1 alpha-2 code in the
     * resolver's user locale (or English when the user locale is empty). Falls
     * back to $iso2 unchanged when the locale doesn't recognise the code —
     * except that ICU always echoes an unrecognised region subtag back
     * upper-cased, so a lower-case, unrecognised $iso2 (e.g. "xx") comes back
     * as "XX", not "xx".
     *
     * @param string $iso2 Alpha-2 country code (case-insensitive).
     */
    public function label(string $iso2): string
    {
        $name = (string) Locale::getDisplayRegion(self::REGION_TAG_PREFIX . $iso2, $this->effectiveLocale());

        return (($name !== '') && ($name !== $iso2)) ? $name : $iso2;
    }

    /**
     * Convert an ISO-3166-1 alpha-2 code to its alpha-3 sibling ("DE" → "DEU").
     * Returns null for anything that is not an alpha-2 code, and for codes ICU
     * does not list.
     *
     * @param string $iso2 Alpha-2 country code (case-insensitive, surrounding whitespace tolerated).
     */
    public function toAlpha3(string $iso2): ?string
    {
        $key = strtoupper(trim($iso2));

        if (!self::isAlpha2Shape($key)) {
            return null;
        }

        return $this->alpha2ToAlpha3Map()[$key] ?? null;
    }

    /**
     * Test-only: clear the three static caches (the reverse name lookup, the
     * alpha-3 bridge memo, and the alpha-2 → alpha-3 table) so each test starts
     * from a clean slate. Not part of the public API.
     *
     * @internal
     */
    public static function clearCache(): void
    {
        self::$reverseLookup     = null;
        self::$alpha3Cache       = [];
        self::$alpha2ToAlpha3Map = null;
    }

    /**
     * Build the reverse name → ISO-2 lookup map, combining every pre-seeded
     * locale plus the user's current locale.
     *
     * @return array<string, string>
     */
    private function lookupMap(): array
    {
        if (self::$reverseLookup === null) {
            self::$reverseLookup = $this->buildReverseLookup();
        }

        return self::$reverseLookup;
    }

    /**
     * @return array<string, string>
     */
    private function buildReverseLookup(): array
    {
        $effective = $this->effectiveLocale();
        $locales   = array_unique([...self::PRESEED_LOCALES, $effective]);

        $map = [];

        foreach (self::ISO2_CODES as $iso2) {
            foreach ($locales as $locale) {
                $name = (string) Locale::getDisplayRegion(self::REGION_TAG_PREFIX . $iso2, $locale);

                if ($name === '') {
                    continue;
                }

                if ($name === $iso2) {
                    continue;
                }

                $normalised = $this->normalise($name);

                if ($normalised === '') {
                    continue;
                }

                // First locale to seed a given normalised name wins.
                $map[$normalised] ??= $iso2;
            }

            // The ISO code itself is a legitimate match — some
            // legacy GEDCOM exports stamp "DE" / "FR" directly
            // into the PLAC field.
            $map[strtolower($iso2)] ??= $iso2;
        }

        // Manual aliases (USA, UK, England, Deutschland, …) win
        // over the ICU defaults so the fixture data and most
        // real-world GEDCOMs resolve without surprises.
        foreach (self::MANUAL_ALIASES as $alias => $iso2) {
            $map[$alias] = $iso2;
        }

        return $map;
    }

    /**
     * ICU's supplemental "codeMappings" table as an alpha-2 → alpha-3 map, so no
     * hand-maintained code table is needed. Each row is (alpha-2, numeric,
     * alpha-3); reading all rows once costs about as much as a single failed
     * scan, so the whole table is materialised on first use.
     *
     * ResourceBundle::create() emits an E_WARNING when the ICU data is
     * unavailable. The scoped `set_error_handler()` below replaces whatever
     * handler the caller has installed — the webtrees request handler, PHPUnit's
     * `failOnWarning="true"` handler, or none at all on the plain CLI — for the
     * duration of the ICU access, and simply swallows the warning; it is
     * restored in the `finally` block regardless of outcome, so the caller's
     * handler is back in place before this method returns. The real safety net
     * is the `instanceof ResourceBundle` checks below, which degrade the
     * array-building loop to an empty map instead of dereferencing a null/false
     * ICU handle; the `catch (Throwable)` covers genuine exceptions the ICU
     * calls can still throw.
     *
     * @return array<string, string>
     */
    private function alpha2ToAlpha3Map(): array
    {
        if (self::$alpha2ToAlpha3Map !== null) {
            return self::$alpha2ToAlpha3Map;
        }

        $map = [];

        set_error_handler(static fn (): bool => true);

        try {
            $bundle   = ResourceBundle::create('supplementalData', 'ICUDATA', false);
            $mappings = $bundle instanceof ResourceBundle ? $bundle->get('codeMappings') : null;

            if ($mappings instanceof ResourceBundle) {
                foreach ($mappings as $row) {
                    if (!$row instanceof ResourceBundle) {
                        continue;
                    }

                    $entries = iterator_to_array($row);
                    $alpha2  = $entries[0] ?? null;
                    $alpha3  = $entries[2] ?? null;

                    // Intersect with the curated set: ICU also lists withdrawn
                    // codes (SU, AN, YU) and CLDR-internal regions (EU -> QUU),
                    // which this class does not treat as countries anywhere else.
                    if (
                        is_string($alpha2)
                        && is_string($alpha3)
                        && in_array($alpha2, self::ISO2_CODES, true)
                    ) {
                        $map[$alpha2] = $alpha3;
                    }
                }
            }
        } catch (Throwable) {
            $map = [];
        } finally {
            restore_error_handler();
        }

        return self::$alpha2ToAlpha3Map = $map;
    }

    /**
     * Lowercase + collapse whitespace + strip leading/trailing dots so "United
     * States", "united states", and " USA. " all hit the same lookup key.
     */
    private function normalise(string $value): string
    {
        $trimmed = trim($value, " \t\n\r\0\x0B.");

        if ($trimmed === '') {
            return '';
        }

        // ICU's display-region output uses U+2019 (right single
        // quotation mark) inside names like "Côte d'Ivoire", but
        // GEDCOM authors typically type the ASCII apostrophe
        // U+0027. Fold the curly variants down to ASCII before
        // case-mapping so the lookup matches either source.
        $quotesNormalised = str_replace(
            ["\u{2019}", "\u{2018}", "\u{02BB}", "\u{02BC}", "\u{201B}"],
            "'",
            $trimmed,
        );

        // strtolower() only handles ASCII; ICU's display-region
        // names contain UTF-8 characters (Ö, Ç, …). Use mb_strtolower
        // so curly-cased non-ASCII letters fold into the lookup map.
        $lower = mb_strtolower($quotesNormalised, 'UTF-8');

        return (string) preg_replace('/\s+/u', ' ', $lower);
    }
}
