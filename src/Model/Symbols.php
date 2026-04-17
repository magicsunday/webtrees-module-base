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
 * Genealogical symbols used in date labels and tooltips across chart modules.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
enum Symbols: string
{
    /**
     * Birth symbol (asterisk).
     */
    case Birth = "\u{2605}";

    /**
     * Death symbol (dagger / obelisk).
     */
    case Death = "\u{2020}";

    /**
     * En dash used as separator in compact date ranges (e.g. "1853–1933").
     */
    case DateRangeSeparator = "\u{2013}";

    /**
     * Placeholder returned when a marriage fact exists but has no date.
     * Consumers that render the marriage symbol can check for this sentinel
     * to display the symbol without an accompanying date string.
     */
    public const string MARRIAGE_DATE_UNKNOWN = '?';
}
