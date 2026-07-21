<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Processor;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Place;
use MagicSunday\Webtrees\ModuleBase\Model\PlaceFormatSpec;
use MagicSunday\Webtrees\ModuleBase\Model\PlaceStyle;
use MagicSunday\Webtrees\ModuleBase\Support\Locale\IsoCountryMap;

use function in_array;
use function is_string;

/**
 * Extracts birth, death, and marriage place names from an individual's life
 * events. Returns both full GEDCOM place strings (for tooltips) and shortened
 * versions formatted according to the configured {@see PlaceFormatSpec} (for
 * arc text).
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
class PlaceProcessor
{
    /**
     * ISO-3166-1 alpha-2 codes of countries whose name coincides with a
     * well-known city name, so a lone place segment carrying one of them is
     * more likely the city than the country (e.g. "Luxembourg", "Monaco",
     * "San Marino"). For these the ISO city styles keep the recorded text
     * rather than collapsing a lone segment to its country code. This is a
     * display-policy list mirroring GVExport's behaviour, not ISO reference
     * data, so it lives here rather than in {@see IsoCountryMap}.
     *
     * @var list<string>
     */
    private const array AMBIGUOUS_CITY_COUNTRIES = [
        'LU', 'MC', 'SM', 'SG', 'PA', 'GT', 'MX', 'KW', 'DJ', 'AD', 'VA', 'GI',
    ];

    /**
     * @param Individual      $individual The individual to process
     * @param PlaceFormatSpec $format     Fully resolved formatting instruction
     * @param IsoCountryMap   $countryMap Resolver used by the styles that resolve a country segment
     */
    public function __construct(
        private readonly Individual $individual,
        private readonly PlaceFormatSpec $format,
        private readonly IsoCountryMap $countryMap,
    ) {
    }

    /**
     * Returns the full GEDCOM birth place name for tooltip display.
     *
     * @return string
     */
    public function getBirthPlace(): string
    {
        return $this->fullPlaceName($this->individual->getBirthPlace());
    }

    /**
     * Returns the full GEDCOM death place name for tooltip display.
     *
     * @return string
     */
    public function getDeathPlace(): string
    {
        return $this->fullPlaceName($this->individual->getDeathPlace());
    }

    /**
     * Returns the full GEDCOM marriage place name (from the individual's first
     * spouse family) for tooltip display. Empty string when no spouse family
     * exists.
     *
     * @return string
     */
    public function getMarriagePlace(): string
    {
        $family = $this->individual->spouseFamilies()->first();

        if ($family === null) {
            return '';
        }

        return $this->fullPlaceName($family->getMarriagePlace());
    }

    /**
     * Returns the birth place name shortened according to the configured
     * {@see PlaceFormatSpec}.
     *
     * @return string
     */
    public function getBirthPlaceShort(): string
    {
        return $this->shortPlaceName($this->individual->getBirthPlace());
    }

    /**
     * Returns the death place name shortened according to the configured
     * {@see PlaceFormatSpec}.
     *
     * @return string
     */
    public function getDeathPlaceShort(): string
    {
        return $this->shortPlaceName($this->individual->getDeathPlace());
    }

    /**
     * Returns the unmodified GEDCOM place name string.
     *
     * @param Place $place
     *
     * @return string
     */
    private function fullPlaceName(Place $place): string
    {
        return $place->gedcomName();
    }

    /**
     * Returns a shortened place name according to the configured format. The
     * empty-name guard stays first: a place without a recorded name has no
     * segments to format.
     *
     * @param Place $place
     *
     * @return string
     */
    public function shortPlaceName(Place $place): string
    {
        $placeName = $place->gedcomName();

        if ($placeName === '') {
            return '';
        }

        return match ($this->format->style) {
            PlaceStyle::Full        => $placeName,
            PlaceStyle::Levels      => $this->levelParts($place),
            PlaceStyle::CityCountry => $this->cityAndCountry($place),
            PlaceStyle::CityIso2    => $this->cityAndIsoCode($place, false),
            PlaceStyle::CityIso3    => $this->cityAndIsoCode($place, true),
        };
    }

    /**
     * Keep a fixed number of hierarchy levels, from either end.
     *
     * @param Place $place
     *
     * @return string
     */
    private function levelParts(Place $place): string
    {
        $parts = $this->format->fromEnd
            ? $place->lastParts($this->format->levels)
            : $place->firstParts($this->format->levels);

        return $parts->implode(', ');
    }

    /**
     * Keep the first and the last segment. A country recorded as a three-letter
     * code is spelled out in the user's language; a two-letter segment is left
     * alone, {@see self::spellOutCode()} explains why.
     *
     * @param Place $place
     *
     * @return string
     */
    private function cityAndCountry(Place $place): string
    {
        $segments = $this->outerSegments($place);

        if ($segments === null) {
            return '';
        }

        if ($segments['last'] === null) {
            return $segments['first'];
        }

        return $segments['first'] . ', ' . $this->spellOutCode($segments['last']);
    }

    /**
     * Keep the first segment and render the country as an ISO-3166-1 code —
     * alpha-2 when $alpha3 is false, alpha-3 otherwise. A multi-segment place
     * resolves its last segment; a lone segment is treated as the country
     * itself, unlike {@see self::cityAndCountry()} which returns it unchanged.
     * Every resolution failure degrades to the recorded text.
     *
     * @param Place $place  The place to shorten
     * @param bool  $alpha3 Whether to render the alpha-3 code instead of alpha-2
     *
     * @return string
     */
    private function cityAndIsoCode(Place $place, bool $alpha3): string
    {
        $segments = $this->outerSegments($place);

        if ($segments === null) {
            return '';
        }

        if ($segments['last'] === null) {
            return $this->loneCountryCode($segments['first'], $alpha3);
        }

        return $segments['first'] . ', ' . $this->countrySegmentCode($segments['last'], $alpha3);
    }

    /**
     * Render a multi-segment place's country segment as an ISO code. The
     * segment is resolved to its alpha-2 code and, for $alpha3, bridged on to
     * the alpha-3 code; an unresolvable segment or a missing alpha-3 mapping
     * degrades to the recorded text.
     *
     * @param string $segment The last (country) segment
     * @param bool   $alpha3  Whether to render the alpha-3 code instead of alpha-2
     *
     * @return string
     */
    private function countrySegmentCode(string $segment, bool $alpha3): string
    {
        $iso2 = $this->countryMap->resolve($segment);

        if ($iso2 === null) {
            return $segment;
        }

        return $this->isoCode($iso2, $alpha3, $segment);
    }

    /**
     * Render a lone segment as its ISO country code. The segment is treated as
     * the country name (GVExport parity), but an ambiguous city/country name
     * keeps its recorded text, as does an unresolvable segment or a missing
     * alpha-3 mapping.
     *
     * @param string $segment The lone place segment
     * @param bool   $alpha3  Whether to render the alpha-3 code instead of alpha-2
     *
     * @return string
     */
    private function loneCountryCode(string $segment, bool $alpha3): string
    {
        $iso2 = $this->countryMap->resolve($segment);

        if (($iso2 === null) || in_array($iso2, self::AMBIGUOUS_CITY_COUNTRIES, true)) {
            return $segment;
        }

        return $this->isoCode($iso2, $alpha3, $segment);
    }

    /**
     * Turn a resolved alpha-2 code into the requested ISO representation: the
     * alpha-2 code itself, or its alpha-3 sibling when $alpha3 is set. A missing
     * alpha-3 mapping degrades to $fallback, the recorded segment text.
     *
     * @param string $iso2     Resolved ISO-3166-1 alpha-2 code
     * @param bool   $alpha3   Whether to render the alpha-3 code instead of alpha-2
     * @param string $fallback Recorded segment text to fall back to when the alpha-3 mapping is missing
     *
     * @return string
     */
    private function isoCode(string $iso2, bool $alpha3, string $fallback): string
    {
        if (!$alpha3) {
            return $iso2;
        }

        return $this->countryMap->toAlpha3($iso2) ?? $fallback;
    }

    /**
     * The outer segments of a place: the first one, plus the last one when the
     * place actually has a second segment. A single-segment place yields a null
     * "last", which each style that resolves a country segment handles its own
     * way. Returns null when the place has no usable segment at all.
     *
     * @param Place $place
     *
     * @return array{first: string, last: string|null}|null
     */
    private function outerSegments(Place $place): ?array
    {
        $first = $place->firstParts(1)->first();

        if (!is_string($first)) {
            return null;
        }

        $last = $place->lastParts(1)->first();

        if (
            !is_string($last)
            || ($place->firstParts(2)->count() < 2)
        ) {
            return ['first' => $first, 'last' => null];
        }

        return ['first' => $first, 'last' => $last];
    }

    /**
     * Expand a three-letter country code into its localised name. Any other
     * segment is returned unchanged. The restriction to exactly three letters
     * is deliberate: two-letter segments are ambiguous with US and German
     * state abbreviations, so "Dover, DE" is Delaware and "Ulm, BW" is
     * Baden-Württemberg, not country codes. Note that the resolver also
     * accepts the Chapman codes webtrees treats as countries, so "ENG"
     * resolves to the United Kingdom. The Chapman code space also contains
     * three-letter county codes that collide with an ISO 3166-1 alpha-3
     * country code (e.g. "KEN" for Kent vs. Kenya, "SOM" for Somerset vs.
     * Somalia); this is not guarded against, because a county practically
     * never occupies the final segment where the country belongs.
     *
     * @param string $segment
     *
     * @return string
     */
    private function spellOutCode(string $segment): string
    {
        if (!IsoCountryMap::isAlpha3Shape($segment)) {
            return $segment;
        }

        $iso2 = $this->countryMap->resolve($segment);

        return $iso2 === null ? $segment : $this->countryMap->label($iso2);
    }
}
