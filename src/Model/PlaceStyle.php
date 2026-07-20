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
 * The axis along which a place name is shortened for display. Distinct from the
 * user-facing choice list ({@see PlaceFormatChoice}): "Automatic" is a source of
 * settings, not a way of formatting, and is resolved before it reaches a
 * formatter.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
enum PlaceStyle
{
    /**
     * Keep the place name as recorded.
     */
    case Full;

    /**
     * Keep a fixed number of hierarchy levels, from either end.
     */
    case Levels;
}
