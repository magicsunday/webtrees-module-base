<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Catalogue;

/**
 * A fuzzy entry with its translation corrected, as it goes into the overlay.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class ResolvedEntry
{
    /**
     * @param string $text        The entry as it is written in the overlay
     * @param bool   $rewritten   Whether the translation was changed
     * @param string $translation The translation of the entry as it is written in the overlay
     */
    public function __construct(
        public string $text,
        public bool $rewritten,
        public string $translation,
    ) {
    }
}
