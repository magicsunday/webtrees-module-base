<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Catalogue;

use function array_key_exists;
use function array_map;
use function explode;
use function mb_strimwidth;
use function preg_match;
use function str_starts_with;
use function substr;
use function trim;

/**
 * Reads one block of a catalogue in the canonical layout into its flags, comments and literals.
 *
 * The resolver and the entry reader both work on the same canonical text, so they share this one reader.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class CatalogueBlockParser
{
    /**
     * Static-only class, it is never instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Reads the flags and the literals of one entry without interpreting them.
     *
     * @param string $block One entry as it is written in the file, without the blank line after it
     *
     * @return ParsedEntry The flags of the entry, its translator comments and its literals by field name
     */
    public static function parse(string $block): ParsedEntry
    {
        $flags     = [];
        $comments  = [];
        $fields    = [];
        $field     = null;
        $irregular = null;
        $obsolete  = false;

        foreach (explode("\n", $block) as $line) {
            if (str_starts_with($line, '#~')) {
                $obsolete = true;
            }

            if (str_starts_with($line, '#,')) {
                $flags = [...$flags, ...array_map(trim(...), explode(',', substr($line, 2)))];

                continue;
            }

            if (
                str_starts_with($line, '#')
                && !str_starts_with($line, '#.')
                && !str_starts_with($line, '#:')
                && !str_starts_with($line, '#|')
                && !str_starts_with($line, '#~')
            ) {
                $comments[] = $line;

                continue;
            }

            $prefix = '';
            $text   = $line;

            if (str_starts_with($line, '#| ')) {
                $prefix = 'previous ';
                $text   = substr($line, 3);
            } elseif (str_starts_with($line, '#')) {
                $field = null;

                continue;
            } elseif (
                ($field !== null)
                && str_starts_with($field, 'previous ')
            ) {
                // A literal of the previous text is written with its own marker, so a bare one belongs to no field.
                $field = null;
            }

            if (preg_match('/^(msgctxt|msgid_plural|msgid|msgstr(?:\[\d+])?) "((?:[^"\\\\]++|\\\\.)*+)"$/', $text, $matches) === 1) {
                $field = $prefix . $matches[1];

                if (array_key_exists($field, $fields)) {
                    $irregular ??= 'a second "' . $field . '" in one entry';
                }

                $fields[$field] = $matches[2];
            } elseif (
                ($field !== null)
                && (preg_match('/^"((?:[^"\\\\]++|\\\\.)*+)"$/', $text, $matches) === 1)
            ) {
                $fields[$field] .= $matches[1];
            } else {
                $irregular ??= 'a line that is no comment, keyword line or literal: ' . mb_strimwidth(PrintableText::line($line), 0, 80, '…');
            }
        }

        return new ParsedEntry($flags, $comments, $fields, $irregular, $obsolete);
    }
}
