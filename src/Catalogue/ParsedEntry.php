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
 * The flags, comments and literals of one block of a catalogue, read without interpreting them.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class ParsedEntry
{
    /**
     * @param list<string>          $flags     The flags of the entry
     * @param list<string>          $comments  The translator comments of the entry
     * @param array<string, string> $fields    The literals by field name, a previous field prefixed with "previous "
     * @param string|null           $irregular The reason the block is not laid out as the canonical layout has it, or null
     * @param bool                  $obsolete  Whether a line of the block marks an obsolete entry
     */
    public function __construct(
        public array $flags,
        public array $comments,
        public array $fields,
        public ?string $irregular,
        public bool $obsolete,
    ) {
    }
}
