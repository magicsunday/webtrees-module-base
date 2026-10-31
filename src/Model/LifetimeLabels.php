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
 * The texts of the lifetime label of a person who has no complete lifetime range.
 *
 * The library has no translation catalogue, so it never translates. The consuming module builds
 * this object at its own call site from `I18N::translate()` literals, where xgettext finds them
 * and its catalogue holds them.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class LifetimeLabels
{
    /**
     * @param string $bornFormat The text for a person with a birth year only, with one `%s` for the year
     * @param string $diedFormat The text for a person with a death year only, with one `%s` for the year
     * @param string $deceased   The text for a deceased person without a birth or death year
     */
    public function __construct(
        public string $bornFormat,
        public string $diedFormat,
        public string $deceased,
    ) {
    }
}
