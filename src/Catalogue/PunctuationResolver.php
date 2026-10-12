<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Catalogue;

use function array_diff;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function mb_strimwidth;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function sort;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function substr;
use function trim;

/**
 * Finds the fuzzy catalogue entries whose source text changed in punctuation or spacing only.
 *
 * A fuzzy entry is left out of the compiled catalogue, so the English source shows instead
 * of a translation that is still right. A change of a dash, a middle dot or a space does
 * not change the meaning of a translation, so the same swap is applied to it and the entry
 * counts as resolved. Every other fuzzy entry stays fuzzy and is reported, because a person
 * has to judge it.
 *
 * The resolver only reads. It works on the literals as they are written in the file, with
 * their escapes, so it neither unescapes nor escapes anything, and it does not lay out a
 * catalogue. The corrected entries come back as an overlay that the gettext tools apply, so
 * the layout stays the one they write.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class PunctuationResolver
{
    /**
     * The separators that count as the same punctuation. A source text that swaps one for another
     * swaps it in the translation too.
     *
     * @var list<string>
     */
    private const array SEPARATORS = [' — ', ' – ', ' - ', ' · '];

    /**
     * Splits a catalogue into its entries and resolves the fuzzy ones that only differ in punctuation.
     *
     * @param string $catalogue The text of a messages.po in the canonical layout of gettext, as the toolchain reads it
     *
     * @return Resolution The overlay with the resolved entries and the source texts that stay fuzzy
     *
     * @throws CatalogueLayoutException When a block of the catalogue is not laid out as the canonical layout has it
     */
    public function resolve(string $catalogue): Resolution
    {
        if (trim($catalogue) === '') {
            return new Resolution('', '', 0, [], [], []);
        }

        $header      = '';
        $entryBehind = false;
        $resolved    = [];
        $unresolved  = [];
        $rewritten   = [];
        $live        = [];

        foreach (explode("\n\n", trim($catalogue, "\n")) as $block) {
            $entry = $this->parse($block);

            if ($entry->irregular !== null) {
                throw new CatalogueLayoutException($entry->irregular);
            }

            $msgid = $entry->fields['msgid'] ?? null;

            // The canonical layout holds a block without a source text for an obsolete entry only.
            if ($msgid === null) {
                if ($entry->obsolete) {
                    continue;
                }

                throw new CatalogueLayoutException('a block without a source text');
            }

            if (
                !array_key_exists('msgstr', $entry->fields)
                && !array_key_exists('msgstr[0]', $entry->fields)
            ) {
                throw new CatalogueLayoutException('an entry without a translation field');
            }

            // An empty source text with a context is an entry of its own, only the header has none.
            if (
                ($msgid === '')
                && !array_key_exists('msgctxt', $entry->fields)
            ) {
                // The comments in front of an entry would move onto a header that stands behind it.
                if (
                    ($header !== '')
                    || $entryBehind
                ) {
                    throw new CatalogueLayoutException('a header that is not the first block, or a second header');
                }

                $header = $block;

                continue;
            }

            $entryBehind = true;
            $translation = null;
            $fuzzy       = in_array('fuzzy', $entry->flags, true);

            if ($fuzzy) {
                $replacement = $this->resolveEntry($entry->fields, $entry->comments, $entry->flags);

                if ($replacement instanceof ResolvedEntry) {
                    $resolved[]  = $replacement->text;
                    $translation = $replacement->translation;

                    if ($replacement->rewritten) {
                        $rewritten[] = $msgid;
                    }
                } else {
                    $unresolved[] = $msgid;
                }
            }

            $live[] = $this->identity($entry->fields, $translation, $fuzzy && ($translation === null));
        }

        if ($resolved === []) {
            return new Resolution('', '', 0, $unresolved, [], $live);
        }

        // The tools need the charset of the header.
        if ($header === '') {
            throw new CatalogueLayoutException('a catalogue without a header entry');
        }

        $entries = implode("\n\n", $resolved) . "\n";
        $overlay = $header . "\n\n";

        return new Resolution(
            $overlay . $entries,
            $entries,
            count($resolved),
            $unresolved,
            $rewritten,
            $live,
        );
    }

    /**
     * Builds the identity of a live entry from its context, its source texts, its translations and whether it is
     * fuzzy.
     *
     * The translations and the fuzzy state belong to it, so an entry whose translation the last merge loses or
     * changes, or that it marks fuzzy, is a different one. A resolved entry stands with the translation it is given
     * and is not fuzzy.
     *
     * @param array<string, string> $fields      The literals of the entry by field name
     * @param string|null           $translation The translation of a resolved entry, null to take the written ones
     * @param bool                  $fuzzy       Whether the entry is fuzzy once the run is done
     *
     * @return string The identity of the entry
     */
    private function identity(array $fields, ?string $translation, bool $fuzzy): string
    {
        $translations = [];

        foreach ($fields as $name => $value) {
            if (preg_match('/^msgstr(?:\[\d+])?$/', $name) === 1) {
                $translations[] = $name . '=' . (($name === 'msgstr') && ($translation !== null) ? $translation : $value);
            }
        }

        sort($translations);

        return (array_key_exists('msgctxt', $fields) ? 'c' . $fields['msgctxt'] : 'n')
            . "\x04" . ($fields['msgid'] ?? '')
            . "\x04" . ($fields['msgid_plural'] ?? '')
            . "\x04" . implode("\x04", $translations)
            . "\x04" . ($fuzzy ? 'fuzzy' : 'clear');
    }

    /**
     * Reads the flags and the literals of one entry without interpreting them.
     *
     * @param string $block One entry as it is written in the file, without the blank line after it
     *
     * @return ParsedEntry The flags of the entry, its translator comments and its literals by field name
     */
    private function parse(string $block): ParsedEntry
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

    /**
     * Builds the corrected entry for a fuzzy entry that only differs in punctuation.
     *
     * @param array<string, string> $fields   The literals of the fuzzy entry by field name
     * @param list<string>          $comments The translator comments of the entry, which the overlay keeps
     * @param list<string>          $flags    The flags of the entry, of which the overlay keeps all but the fuzzy flag
     *
     * @return ResolvedEntry|null The entry with its corrected translation, or null when a person has to judge it
     */
    private function resolveEntry(array $fields, array $comments, array $flags): ?ResolvedEntry
    {
        $msgid    = $fields['msgid'] ?? '';
        $previous = $fields['previous msgid'] ?? '';
        $msgstr   = $fields['msgstr'] ?? '';

        if (
            ($msgstr === '')
            || ($previous === '')
            || ($previous === $msgid)
            || array_key_exists('previous msgid_plural', $fields)
            || (($fields['previous msgctxt'] ?? null) !== ($fields['msgctxt'] ?? null))
            || $this->endsInBareMark($previous)
            || $this->endsInBareMark($msgid)
            || ($this->normalise($previous) !== $this->normalise($msgid))
        ) {
            return null;
        }

        $lines  = $comments;
        $others = array_values(array_diff($flags, ['fuzzy']));

        if ($others !== []) {
            $lines[] = '#, ' . implode(', ', $others);
        }

        if (array_key_exists('msgctxt', $fields)) {
            $lines[] = 'msgctxt "' . $fields['msgctxt'] . '"';
        }

        $swapped = $this->swapSeparators($msgstr, $previous, $msgid);
        $swapped = ($swapped === null) ? null : $this->mirrorEdgeSpace($swapped, $previous, $msgid);

        if ($swapped === null) {
            return null;
        }

        $lines[] = 'msgid "' . $msgid . '"';
        $lines[] = 'msgstr "' . $swapped . '"';

        return new ResolvedEntry(implode("\n", $lines), $swapped !== $msgstr, $swapped);
    }

    /**
     * Tells whether a text starts or ends with a separator mark that has no space next to it.
     *
     * The swap of the separators sees only marks with a space on both sides, so such a mark at an edge would be
     * compared as equal and left in the old order in the translation.
     *
     * @param string $text A source text as written in the catalogue
     *
     * @return bool True when the first or the last character is one of the marks
     */
    private function endsInBareMark(string $text): bool
    {
        return preg_match('/^[—–\-·]|[—–\-·]$/u', $text) === 1;
    }

    /**
     * Reduces a text to a form in which every separator variant and every run of spaces is the same.
     *
     * @param string $text A source text as written in the catalogue
     *
     * @return string The text with the separators unified and the spaces collapsed
     */
    private function normalise(string $text): string
    {
        $unified = str_replace(self::SEPARATORS, ' · ', $text);

        return trim(preg_replace('/\s+/', ' ', $unified) ?? $unified);
    }

    /**
     * Applies to a translation the separator swap that the source text went through.
     *
     * The swap is read from the separators of the old and the new source text in the order they stand. It is
     * only clear when one separator was replaced by one other at every place it stood and nothing else moved.
     * The translation must hold the separators of the old source text in the same order, hold the new ones
     * already or hold none of the one that changed.
     * Otherwise the translation cannot be corrected without guessing which of its separators belongs to the
     * change.
     *
     * @param string $translation The translation as written in the catalogue
     * @param string $previous    The source text before the change
     * @param string $current     The source text after the change
     *
     * @return string|null The translation with the same separator swap, or null when the swap is ambiguous
     */
    private function swapSeparators(string $translation, string $previous, string $current): ?string
    {
        $before = $this->separatorsOf($previous);
        $after  = $this->separatorsOf($current);

        if ($before === $after) {
            return $translation;
        }

        foreach ($before as $position => $separator) {
            if ($separator === ($after[$position] ?? null)) {
                continue;
            }

            $replacement = $after[$position] ?? '';
            $replaced    = array_map(
                static fn (string $candidate): string => ($candidate === $separator) ? $replacement : $candidate,
                $before,
            );

            if ($replaced !== $after) {
                return null;
            }

            $inTranslationSequence = $this->separatorsOf($translation);

            // The separators of a translation correspond to those of the source text only when they stand in the
            // same order. A translation that holds the separator more or less often, a range of years for
            // example, or that leaves out or moves another one, cannot be told apart from one that changed
            // nothing, so no swap can say which separator belongs to the change.
            $untouched = array_values(
                array_filter(
                    $before,
                    static fn (string $candidate): bool => $candidate !== $separator,
                ),
            );

            if (!in_array($inTranslationSequence, [$before, $untouched, $after], true)) {
                return null;
            }

            $swapped = str_replace($separator, $replacement, $translation);

            // Two overlapping separators are counted as two but replaced as one, and one stays behind.
            if (str_contains($swapped, $separator)) {
                return null;
            }

            // The canonical text writes a tab, a line feed and the like as an escape, so for the checks below such an
            // escape is read as the space it stands for.
            $plain = preg_replace('/((?<!\\\\)(?:\\\\\\\\)*+)\\\\[tnrfv]/', '$1 ', $swapped);

            if ($plain === null) {
                return null;
            }

            // A translation that writes the sign of the changed separator in another spacing, a dash without
            // spaces for example, is not one that left the separator out, so the old sign would stay behind. A
            // hyphen inside a word is no such sign. One that has a space or an edge of the text on one side is.
            $sign   = trim($separator);
            $signIn = ($sign === '-')
                ? (preg_match('/(?<![^\s])-|-(?![^\s])/u', $plain) !== 0)
                : str_contains($plain, $sign);

            // A mark of the family that has a space other than the plain one next to it, a no-break space or a tab
            // for example, is not read as a separator at all, so the translation cannot be told to follow the
            // change.
            $marks      = '[\x{2014}\x{2013}\x{B7}\-]';
            $leftSpace  = '(?:(?!\x{20})\s|(?<!\\\\)(?:\\\\\\\\)*+\\\\[tnrfv])';
            $rightSpace = '(?:(?!\x{20})\s|\\\\[tnrfv])';
            $oddSpace   = preg_match('/' . $leftSpace . $marks . '|' . $marks . $rightSpace . '/u', $swapped) !== 0;

            return ($signIn || $oddSpace) ? null : $swapped;
        }

        return null;
    }

    /**
     * Gives a translation the leading and trailing space that the source text went through.
     *
     * Such a space is part of the text, because a source text is joined with others when it is shown. A translation
     * that already has the new space is left alone, one that has the old space follows the change, and one that has
     * neither cannot be corrected without guessing. A text that is nothing but space has no edge to tell apart.
     *
     * @param string $translation The translation as written in the catalogue
     * @param string $previous    The source text before the change
     * @param string $current     The source text after the change
     *
     * @return string|null The translation with the space of the new source text, or null when it cannot be told
     */
    private function mirrorEdgeSpace(string $translation, string $previous, string $current): ?string
    {
        if (
            $this->isBlank($translation)
            || $this->isBlank($previous)
            || $this->isBlank($current)
        ) {
            return null;
        }

        [$translationLeading, $translationTrailing] = $this->edgeSpace($translation);
        [$previousLeading, $previousTrailing]       = $this->edgeSpace($previous);
        [$currentLeading, $currentTrailing]         = $this->edgeSpace($current);

        $leading  = $this->followEdge($translationLeading, $previousLeading, $currentLeading);
        $trailing = $this->followEdge($translationTrailing, $previousTrailing, $currentTrailing);

        if (
            ($leading === null)
            || ($trailing === null)
        ) {
            return null;
        }

        // An escaped line feed or tab, or a space that is no plain one, at an edge of the translation is its own edge,
        // so the translation is not made to follow a change of the space at that edge of the source text.
        if (
            (($previousLeading !== $currentLeading) && (preg_match('/\A\s*(?:\\\\[tnrfv]|(?!\x{20})\s)/u', $translation) === 1))
            || (($previousTrailing !== $currentTrailing) && (preg_match('/(?:(?<!\\\\)(?:\\\\\\\\)*+\\\\[tnrfv]|(?!\x{20})\s)\s*\z/u', $translation) === 1))
        ) {
            return null;
        }

        return $leading . (preg_replace('/\A\s+|\s+\z/', '', $translation) ?? $translation) . $trailing;
    }

    /**
     * Decides the space at one edge of a translation.
     *
     * @param string $inTranslation The space the translation has at the edge
     * @param string $before        The space the source text had at the edge
     * @param string $after         The space the source text has at the edge
     *
     * @return string|null The space the translation gets, or null when it matches neither source text
     */
    private function followEdge(string $inTranslation, string $before, string $after): ?string
    {
        if (
            ($before === $after)
            || ($inTranslation === $after)
        ) {
            return $inTranslation;
        }

        return ($inTranslation === $before) ? $after : null;
    }

    /**
     * Reads the space at the start and at the end of a text.
     *
     * @param string $text The text to read, which holds a character that is no space
     *
     * @return array{string, string} The leading and the trailing space, each empty when the text has none there
     */
    private function edgeSpace(string $text): array
    {
        preg_match('/\A\s*/', $text, $leading);
        preg_match('/\s*\z/', $text, $trailing);

        return [$leading[0] ?? '', $trailing[0] ?? ''];
    }

    /**
     * Tells whether a text holds nothing but space.
     *
     * @param string $text The text to check
     *
     * @return bool True when the text has no character that is no space
     */
    private function isBlank(string $text): bool
    {
        return preg_match('/\S/', $text) !== 1;
    }

    /**
     * Lists the separators of a source text in the order they stand.
     *
     * @param string $text A source text as written in the catalogue
     *
     * @return list<string> The separators found, one entry per place
     */
    private function separatorsOf(string $text): array
    {
        $alternatives = array_map(
            static fn (string $separator): string => preg_quote($separator, '/'),
            self::SEPARATORS,
        );

        // The lookahead lets two separators that share a space both match.
        $pattern = '/(?=(' . implode('|', $alternatives) . '))/';

        preg_match_all($pattern, $text, $matches);

        return $matches[1];
    }
}
