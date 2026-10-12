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
use function count;
use function implode;
use function in_array;
use function is_string;
use function sprintf;

/**
 * Plans which translations a catalogue gets from a translations file.
 *
 * Only an entry that is not fuzzy and has no translation at all is written. An entry that
 * webtrees core already translates is left alone, and so is one the translations file does
 * not hold for the locale. A plural entry is written only when the translations file lists
 * exactly as many strings as the plural rule of the locale has forms, and a singular entry
 * only when it lists one string. An entry that does not fit is refused and named.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class TranslationFiller
{
    /**
     * Plans the translations of one catalogue.
     *
     * @param array{header: string, entries: array<string, array{context: ?string, msgid: string, plural: ?string, forms: list<string>, fuzzy: bool, comments: list<string>, flags: list<string>}>} $catalogue    The catalogue as the reader returns it
     * @param array<string, array<string, string|list<string>>>                                                                                                                                     $translations The translations file, by key and locale
     * @param string                                                                                                                                                                                $locale       The locale of the catalogue
     * @param int                                                                                                                                                                                   $forms        The number of plural forms of the locale
     * @param array<string, true>                                                                                                                                                                   $coreOwned    The keys webtrees core translates itself
     *
     * @return array{overlay: string, selection: string, written: int, refused: list<string>, expected: array<string, list<string>>}
     *                                                                                                                               The overlay and the selection for the toolchain, the number of entries
     *                                                                                                                               written, the refused entries with their reason and the translations the
     *                                                                                                                               catalogue must hold afterwards
     */
    public function plan(array $catalogue, array $translations, string $locale, int $forms, array $coreOwned): array
    {
        $blocks   = [];
        $refused  = [];
        $expected = [];

        foreach ($catalogue['entries'] as $key => $entry) {
            if (
                $entry['fuzzy']
                || !$this->isEmpty($entry['forms'])
                || array_key_exists($key, $coreOwned)
            ) {
                continue;
            }

            $value = $translations[$key][$locale] ?? null;

            if ($value === null) {
                continue;
            }

            $wanted = ($entry['plural'] === null) ? 1 : $forms;
            $given  = is_string($value) ? [$value] : $value;

            if (
                ($entry['plural'] === null)
                !== is_string($value)
            ) {
                $refused[] = sprintf(
                    '%s: "%s" is %s but its translation is %s',
                    $locale,
                    $this->name($entry),
                    ($entry['plural'] === null) ? 'a singular text' : 'a plural text',
                    is_string($value) ? 'one string' : 'a list',
                );

                continue;
            }

            if (count($given) !== $wanted) {
                $refused[] = sprintf(
                    '%s: "%s" needs %d forms and the translations file lists %d',
                    $locale,
                    $this->name($entry),
                    $wanted,
                    count($given),
                );

                continue;
            }

            if ($this->isEmpty($given)) {
                $refused[] = sprintf('%s: "%s" has an empty translation', $locale, $this->name($entry));

                continue;
            }

            if (in_array('', $given, true)) {
                $refused[] = sprintf('%s: "%s" lists an empty form next to a form with text', $locale, $this->name($entry));

                continue;
            }

            $blocks[]       = $this->block($entry, $given);
            $expected[$key] = $given;
        }

        $overlay = ($blocks === []) ? '' : $catalogue['header'] . "\n\n" . implode("\n\n", $blocks) . "\n";

        return [
            'overlay'   => $overlay,
            'selection' => ($blocks === []) ? '' : implode("\n\n", $blocks) . "\n",
            'written'   => count($blocks),
            'refused'   => $refused,
            'expected'  => $expected,
        ];
    }

    /**
     * Names an entry for a report line, with its context in front when it has one.
     *
     * @param array{context: ?string, msgid: string, plural: ?string, forms: list<string>, fuzzy: bool, comments: list<string>, flags: list<string>} $entry The entry
     *
     * @return string The context and the source text
     */
    private function name(array $entry): string
    {
        return ($entry['context'] === null) ? $entry['msgid'] : $entry['context'] . '|' . $entry['msgid'];
    }

    /**
     * Tells whether none of the strings holds a text.
     *
     * @param list<string> $forms The strings
     *
     * @return bool True when every string is empty
     */
    private function isEmpty(array $forms): bool
    {
        return implode('', $forms) === '';
    }

    /**
     * Writes one entry with its translations.
     *
     * @param array{context: ?string, msgid: string, plural: ?string, forms: list<string>, fuzzy: bool, comments: list<string>, flags: list<string>} $entry The entry
     * @param list<string>                                                                                                                           $forms The translations
     *
     * @return string The entry as the toolchain reads it
     */
    private function block(array $entry, array $forms): string
    {
        $lines = $entry['comments'];

        if ($entry['flags'] !== []) {
            $lines[] = '#, ' . implode(', ', $entry['flags']);
        }

        if ($entry['context'] !== null) {
            $lines[] = 'msgctxt ' . CatalogueEntryReader::literal($entry['context']);
        }

        $lines[] = 'msgid ' . CatalogueEntryReader::literal($entry['msgid']);

        if ($entry['plural'] === null) {
            $lines[] = 'msgstr ' . CatalogueEntryReader::literal($forms[0]);

            return implode("\n", $lines);
        }

        $lines[] = 'msgid_plural ' . CatalogueEntryReader::literal($entry['plural']);

        foreach ($forms as $index => $form) {
            $lines[] = 'msgstr[' . $index . '] ' . CatalogueEntryReader::literal($form);
        }

        return implode("\n", $lines);
    }
}
