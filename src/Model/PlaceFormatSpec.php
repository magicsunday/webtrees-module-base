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
 * is already a concrete number, so the 0-9 range a tree preference may carry is
 * expressed here rather than encoded into enum cases.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class PlaceFormatSpec
{
    /**
     * @param PlaceStyle $style   How the place name is shortened
     * @param int        $levels  Number of hierarchy levels to keep. Read for PlaceStyle::Levels only; must not be
     *                            negative, and must be at least 1 when style is PlaceStyle::Levels
     * @param bool       $fromEnd When true, the LAST levels are kept (country end) instead of the first. Read for PlaceStyle::Levels only
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
