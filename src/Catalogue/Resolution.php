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
 * The outcome of resolving the fuzzy entries of one catalogue.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class Resolution
{
    /**
     * @param string       $overlay    The catalogue header followed by the resolved entries with their corrected translation,
     *                                 or an empty string when no entry was resolved
     * @param string       $selection  The resolved entries alone, without the header and the comments in front of it,
     *                                 which the tools use to pick the entries to touch
     * @param int          $resolved   The number of entries the overlay holds
     * @param list<string> $unresolved The source texts of the fuzzy entries that stay fuzzy, as written in the catalogue,
     *                                 each with its context and a bar in front when the entry has one
     * @param list<string> $rewritten  The source texts of the resolved entries whose translation was changed, as written in
     *                                 the catalogue, each with its context and a bar in front when the entry has one
     * @param list<string> $live       The identity of every entry that is not obsolete and not the header, made of its
     *                                 context and its source texts, as written in the catalogue
     */
    public function __construct(
        public string $overlay,
        public string $selection,
        public int $resolved,
        public array $unresolved,
        public array $rewritten,
        public array $live,
    ) {
    }
}
