<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Support;

use Fisharebest\Webtrees\I18N;

/**
 * Resolves script direction for arbitrary strings.
 */
final class TextDirection
{
    /**
     * Returns true when the text is written right-to-left.
     */
    public static function isRtl(string $text): bool
    {
        return I18N::scriptDirection(I18N::textScript($text)) === 'rtl';
    }
}
