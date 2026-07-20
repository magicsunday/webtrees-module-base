<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Model;

/**
 * The place-detail options a module offers in its configuration, and the value
 * persisted in the module preference. Deliberately free of display labels: the
 * consuming module supplies those at its own I18N::translate() call sites, where
 * xgettext can extract them.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
enum PlaceFormatChoice: string
{
    /**
     * Inherit level count and direction from the tree preferences.
     */
    case Automatic = 'auto';

    /**
     * Show the place name as recorded.
     */
    case Full = 'full';

    /**
     * Show the lowest hierarchy level only.
     */
    case Levels1 = 'levels-1';

    /**
     * Show the lowest two hierarchy levels.
     */
    case Levels2 = 'levels-2';

    /**
     * Show the lowest three hierarchy levels.
     */
    case Levels3 = 'levels-3';

    /**
     * Show the first and the last segment, both spelled out.
     */
    case CityCountry = 'city-country';

    /**
     * Resolve this choice into a formatter instruction. The two arguments carry
     * the tree's SHOW_PEDIGREE_PLACES / SHOW_PEDIGREE_PLACES_SUFFIX preferences
     * and are read for self::Automatic only — every other case is already fully
     * determined. Resolving here rather than inside the formatter keeps the
     * formatter free of any webtrees configuration dependency.
     *
     * A non-positive tree level count means "no truncation": the preference is
     * an unvalidated database string, so it may be absent (reads as 0) or still
     * hold the pre-3.0 automatic sentinel (-1).
     *
     * @param int  $treeLevels Level count from the tree preference
     * @param bool $treeSuffix Whether the tree keeps the last parts instead of the first
     *
     * @return PlaceFormatSpec
     */
    public function toSpec(int $treeLevels, bool $treeSuffix): PlaceFormatSpec
    {
        return match ($this) {
            self::Automatic   => self::automatic($treeLevels, $treeSuffix),
            self::Full        => new PlaceFormatSpec(PlaceStyle::Full),
            self::Levels1     => new PlaceFormatSpec(PlaceStyle::Levels, 1),
            self::Levels2     => new PlaceFormatSpec(PlaceStyle::Levels, 2),
            self::Levels3     => new PlaceFormatSpec(PlaceStyle::Levels, 3),
            self::CityCountry => new PlaceFormatSpec(PlaceStyle::CityCountry),
        };
    }

    /**
     * The tree-inherited instruction. A non-positive level count covers both the
     * unset preference (reads as 0) and the pre-3.0 automatic sentinel (-1), and
     * means no truncation at all — the spec would reject the negative value.
     *
     * @param int  $treeLevels Level count from the tree preference
     * @param bool $treeSuffix Whether the tree keeps the last parts instead of the first
     *
     * @return PlaceFormatSpec
     */
    private static function automatic(int $treeLevels, bool $treeSuffix): PlaceFormatSpec
    {
        if ($treeLevels <= 0) {
            return new PlaceFormatSpec(PlaceStyle::Full);
        }

        return new PlaceFormatSpec(PlaceStyle::Levels, $treeLevels, $treeSuffix);
    }
}
