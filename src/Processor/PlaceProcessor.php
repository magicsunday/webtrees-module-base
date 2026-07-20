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
