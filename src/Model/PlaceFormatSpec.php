<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Model;

use InvalidArgumentException;

/**
 * A fully resolved place-formatting instruction. Everything a formatter needs,
 * with no configuration lookups left to perform — in particular the level count
 * is already a concrete number: 1-9 when $style is PlaceStyle::Levels (the
 * constructor rejects 0 for that style — a "keep zero levels" instruction is
 * meaningless), 0 for every other style.
 *
 * $levels and $fromEnd are read only when $style is PlaceStyle::Levels. For
 * PlaceStyle::Full and PlaceStyle::CityCountry they are accepted but ignored —
 * this is deliberate, not an oversight: the constructor guards the one
 * direction that would otherwise silently produce a broken formatter
 * (PlaceStyle::Levels with 0 levels), but does not also reject a non-zero
 * $levels or $fromEnd = true passed alongside a style that does not consult
 * them, since doing so carries no formatting risk.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class PlaceFormatSpec
{
    /**
     * @param PlaceStyle $style   How the place name is shortened
     * @param int        $levels  Number of hierarchy levels to keep. Read for PlaceStyle::Levels only, ignored
     *                            otherwise; must not be negative, and must be at least 1 when style is
     *                            PlaceStyle::Levels
     * @param bool       $fromEnd When true, the LAST levels are kept (country end) instead of the first. Read for
     *                            PlaceStyle::Levels only, ignored otherwise
     *
     * @throws InvalidArgumentException When the level count is negative, or zero for PlaceStyle::Levels.
     */
    public function __construct(
        public PlaceStyle $style,
        public int $levels = 0,
        public bool $fromEnd = false,
    ) {
        if ($levels < 0) {
            throw new InvalidArgumentException('The place level count must not be negative.');
        }

        if (($style === PlaceStyle::Levels) && ($levels === 0)) {
            throw new InvalidArgumentException('PlaceStyle::Levels requires at least one level to keep.');
        }
    }
}
