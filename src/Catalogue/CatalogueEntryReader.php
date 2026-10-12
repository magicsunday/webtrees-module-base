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
use function explode;
use function in_array;
use function preg_replace_callback;
use function strtr;
use function trim;

/**
 * Reads the live entries of a catalogue that is in the canonical layout of gettext.
 *
 * It reads what the translation writer needs: the key of an entry, its translations and whether
 * it is fuzzy. Obsolete entries and the header are not entries. The header block is kept as it
 * was written, so an overlay can start with it.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class CatalogueEntryReader
{
    /**
     * Separator of the context and the source text in a key, the one gettext uses in a compiled catalogue.
     */
    private const string KEY_SEPARATOR = "\x04";

    /**
     * Reads the entries of a catalogue.
     *
     * @param string $canonical The catalogue in the canonical layout, as the toolchain returns it
     *
     * @return array{header: string, entries: array<string, array{context: ?string, msgid: string, plural: ?string, forms: list<string>, fuzzy: bool, comments: list<string>, flags: list<string>}>}
     *                                                                                                                                                                                               The header block as written and the live entries by key
     */
    public function read(string $canonical): array
    {
        $header  = '';
        $entries = [];

        foreach (explode("\n\n", $canonical) as $block) {
            if (trim($block) === '') {
                continue;
            }

            $entry = $this->parseBlock($block);

            if ($entry === null) {
                continue;
            }

            if (
                ($entry['msgid'] === '')
                && ($entry['context'] === null)
            ) {
                $header = $block;

                continue;
            }

            $entries[$this->key($entry['context'], $entry['msgid'])] = $entry;
        }

        return ['header' => $header, 'entries' => $entries];
    }

    /**
     * Builds the key of an entry from its context and its source text.
     *
     * @param string|null $context The message context, null for none
     * @param string      $msgid   The source text
     *
     * @return string The key
     */
    private function key(?string $context, string $msgid): string
    {
        return ($context === null) ? $msgid : $context . self::KEY_SEPARATOR . $msgid;
    }

    /**
     * Writes a text as the literal of a catalogue.
     *
     * @param string $text The text
     *
     * @return string The text between double quotes, with the characters escaped that need it
     */
    public static function literal(string $text): string
    {
        $escaped = strtr($text, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\t" => '\\t', "\r" => '\\r', "\x07" => '\\a', "\x08" => '\\b', "\x0C" => '\\f', "\x0B" => '\\v']);

        return '"' . $escaped . '"';
    }

    /**
     * Reads one block of the canonical text.
     *
     * @param string $block The lines of one entry
     *
     * @return array{context: ?string, msgid: string, plural: ?string, forms: list<string>, fuzzy: bool, comments: list<string>, flags: list<string>}|null The entry, or
     *                                                                                                                                                     null for an
     *                                                                                                                                                     obsolete block
     */
    private function parseBlock(string $block): ?array
    {
        $parsed = CatalogueBlockParser::parse($block);

        if (
            $parsed->obsolete
            || !array_key_exists('msgid', $parsed->fields)
        ) {
            return null;
        }

        $forms = [];

        if (array_key_exists('msgstr', $parsed->fields)) {
            $forms[] = $this->unescape($parsed->fields['msgstr']);
        }

        for ($index = 0; array_key_exists('msgstr[' . $index . ']', $parsed->fields); ++$index) {
            $forms[] = $this->unescape($parsed->fields['msgstr[' . $index . ']']);
        }

        return [
            'context'  => array_key_exists('msgctxt', $parsed->fields) ? $this->unescape($parsed->fields['msgctxt']) : null,
            'msgid'    => $this->unescape($parsed->fields['msgid']),
            'plural'   => array_key_exists('msgid_plural', $parsed->fields) ? $this->unescape($parsed->fields['msgid_plural']) : null,
            'forms'    => $forms,
            'fuzzy'    => in_array('fuzzy', $parsed->flags, true),
            'comments' => $parsed->comments,
            'flags'    => $parsed->flags,
        ];
    }

    /**
     * Undoes the escaping of a literal.
     *
     * @param string $literal The text between the quotes as written
     *
     * @return string The text
     */
    private function unescape(string $literal): string
    {
        return (string) preg_replace_callback(
            '/\\\\(.)/s',
            static fn (array $match): string => match ($match[1]) {
                'n'     => "\n",
                't'     => "\t",
                'a'     => "\x07",
                'b'     => "\x08",
                'f'     => "\x0C",
                'v'     => "\x0B",
                'r'     => "\r",
                default => $match[1],
            },
            $literal,
        );
    }
}
