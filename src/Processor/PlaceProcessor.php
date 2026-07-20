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

/**
 * Extracts birth, death, and marriage place names from an individual's life
 * events. Returns both full GEDCOM place strings (for tooltips) and shortened
 * versions truncated to a configurable number of hierarchy levels (for arc
 * text).
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
     */
    public function __construct(
        private readonly Individual $individual,
        private readonly PlaceFormatSpec $format,
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
     * Returns the birth place name truncated to the configured number of
     * hierarchy levels.
     *
     * @return string
     */
    public function getBirthPlaceShort(): string
    {
        return $this->shortPlaceName($this->individual->getBirthPlace());
    }

    /**
     * Returns the death place name truncated to the configured number of
     * hierarchy levels.
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
     * empty-name guard stays first: Place::firstParts() returns an empty
     * collection for an empty place, and ->first() would then yield null.
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
            PlaceStyle::Full   => $placeName,
            PlaceStyle::Levels => $this->levelParts($place),
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
}
