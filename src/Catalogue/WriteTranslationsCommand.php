<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Catalogue;

use Closure;
use DomainException;
use Fisharebest\Localization\Locale;
use JsonException;

use function array_fill_keys;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_slice;
use function basename;
use function count;
use function dirname;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function ltrim;
use function preg_match;
use function realpath;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;

use const JSON_THROW_ON_ERROR;

/**
 * Writes the translations a module keeps in one file into its catalogues.
 *
 * A catalogue gets only the entries that have no translation yet, with exactly as many forms
 * as the plural rule of its locale has. The gettext tools write the catalogue, so its layout
 * is the one of a merge, and a second run changes nothing.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class WriteTranslationsCommand
{
    /**
     * Writes the translations into the catalogues named in the arguments.
     *
     * @param list<string>         $arguments An optional `--core-owned=<file.pot>`, the translations file, the
     *                                        messages.pot, and the messages.po files, in this order
     * @param Closure(string):void $write     Receives every line of the report
     *
     * @return int The exit status, 0 when no catalogue, entry, key or locale was refused, 1 when one was refused
     *             or a file is unusable, 2 when the arguments are too few
     */
    public function run(array $arguments, Closure $write): int
    {
        $coreOwnedFile = null;

        if (
            ($arguments !== [])
            && str_starts_with($arguments[0], '--core-owned=')
        ) {
            $coreOwnedFile = substr($arguments[0], strlen('--core-owned='));
            $arguments     = array_slice($arguments, 1);
        }

        if (count($arguments) < 3) {
            $write('Usage: write-translations [--core-owned=<file.pot>] <translations.json> <messages.pot> <messages.po>...');

            return 2;
        }

        $translationsFile = $arguments[0];
        $template         = $arguments[1];
        $catalogues       = array_slice($arguments, 2);

        $problem = CatalogueFile::unreadable($translationsFile);

        if ($problem !== null) {
            $write(sprintf('  ✘ %s %s', PrintableText::line($translationsFile), $problem));

            return 1;
        }

        $problem = CatalogueFile::unreadable($template);

        if ($problem !== null) {
            $write(sprintf('  ✘ %s %s', PrintableText::line($template), $problem));

            return 1;
        }

        $translations = $this->readTranslations($translationsFile);

        if ($translations === null) {
            $write(
                sprintf(
                    '  ✘ %s is not a JSON object of source texts, each holding a string or a list of strings per locale',
                    PrintableText::line($translationsFile),
                ),
            );

            return 1;
        }

        $toolchain = new CatalogueToolchain();
        $reader    = new CatalogueEntryReader();
        $filler    = new TranslationFiller();

        try {
            $coreOwned = $this->readCoreOwned($coreOwnedFile, $toolchain, $reader);
        } catch (CatalogueToolException $exception) {
            $write(sprintf('  ✘ %s cannot be read: %s', PrintableText::line((string) $coreOwnedFile), PrintableText::line($exception->getMessage())));

            return 1;
        }

        $writtenTotal = 0;
        $refused      = 0;
        $known        = [];
        $seenLocales  = [];

        foreach ($catalogues as $catalogue) {
            $directory = dirname($catalogue);

            if (in_array(basename($directory), ['.', '..'], true)) {
                $resolved  = realpath($directory);
                $directory = ($resolved === false) ? $directory : $resolved;
            }

            $locale = basename($directory);

            // A catalogue was passed for this locale, whatever happens to it, so the locale is not reported as one
            // without a catalogue.
            $seenLocales[$locale] = true;

            $problem = CatalogueFile::unusable($catalogue);

            if ($problem !== null) {
                $write(sprintf('  ✘ %s %s', PrintableText::line($catalogue), $problem));

                ++$refused;

                continue;
            }

            try {
                $forms = Locale::create($locale)->pluralRule()->plurals();
            } catch (DomainException) {
                $write(sprintf('  ✘ %s has no locale webtrees knows, its directory is named "%s"', PrintableText::line($catalogue), PrintableText::line($locale)));

                ++$refused;

                continue;
            }

            try {
                $toolchain->assertMerged($catalogue, $template);

                $parsed = $reader->read($toolchain->normalise($catalogue));
                $known += array_fill_keys(array_keys($parsed['entries']), true);

                $declared = $this->declaredForms($parsed['header']);

                if (
                    ($declared !== null)
                    && ($declared !== $forms)
                ) {
                    $write(
                        sprintf(
                            '  ✘ %s declares %d plural forms but the locale "%s" has %d, correct the header before writing translations',
                            PrintableText::line($catalogue),
                            $declared,
                            PrintableText::line($locale),
                            $forms,
                        ),
                    );

                    ++$refused;

                    continue;
                }

                $plan = $filler->plan($parsed, $translations, $locale, $forms, $coreOwned);

                foreach ($plan['refused'] as $reason) {
                    $write('  ✘ ' . PrintableText::line($reason));

                    ++$refused;
                }

                if (
                    ($declared === null)
                    && $this->writesPluralEntry($plan['expected'], $parsed['entries'])
                ) {
                    $write(
                        sprintf(
                            '  ✘ %s declares no plural forms, add the Plural-Forms header before writing plural translations',
                            PrintableText::line($catalogue),
                        ),
                    );

                    ++$refused;

                    continue;
                }

                if ($plan['written'] === 0) {
                    continue;
                }

                $resolution = new Resolution($plan['overlay'], $plan['selection'], $plan['written'], [], [], []);
                $accepted   = $toolchain->apply(
                    $catalogue,
                    $template,
                    $resolution,
                    fn (string $finished): bool => $this->holdsExactly($reader->read($finished), $parsed, $plan['expected']),
                );

                if (!$accepted) {
                    $write(sprintf('  ✘ %s was left as it is, because the written result differs from the translations given or from the entries the run did not touch', PrintableText::line($catalogue)));

                    ++$refused;

                    continue;
                }

                $writtenTotal += $plan['written'];

                $write(sprintf('  ▸ %-8s written=%3d', PrintableText::line($locale), $plan['written']));
            } catch (CatalogueNotMergedException $exception) {
                $write(
                    sprintf(
                        '  ✘ %s cannot be used as it is, write it once with `msgmerge --previous --output-file=<new file> <catalogue> <template>` and replace it: %s',
                        PrintableText::line($catalogue),
                        PrintableText::line($exception->getMessage()),
                    ),
                );

                ++$refused;
            } catch (CatalogueToolException $exception) {
                $write(sprintf('  ✘ %s could not be written: %s', PrintableText::line($catalogue), PrintableText::line($exception->getMessage())));

                ++$refused;
            }
        }

        if ($known !== []) {
            foreach (array_keys($translations) as $key) {
                if (!array_key_exists((string) $key, $known)) {
                    $write(sprintf('  ✘ "%s" of the translations file matches no entry of the catalogues', PrintableText::line((string) $key)));

                    ++$refused;
                }
            }
        }

        $locales = [];

        foreach ($translations as $byLocale) {
            $locales += array_fill_keys(array_keys($byLocale), true);
        }

        foreach (array_keys($locales) as $locale) {
            if (
                !array_key_exists((string) $locale, $seenLocales)
            ) {
                $write(sprintf('  ✘ the locale "%s" of the translations file matches no catalogue', PrintableText::line((string) $locale)));

                ++$refused;
            }
        }

        $write(sprintf('  ✔ write-translations: written=%d, refused=%d', $writtenTotal, $refused));

        return ($refused === 0) ? 0 : 1;
    }

    /**
     * Reads the translations file and narrows it to the shape the writer works on.
     *
     * @param string $file The path of the translations file
     *
     * @return array<string, array<string, string|list<string>>>|null The translations, null when the file does not have the shape
     */
    private function readTranslations(string $file): ?array
    {
        $content = file_get_contents($file);

        if ($content === false) {
            return null;
        }

        try {
            $decoded = json_decode($content, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (
            !is_array($decoded)
            || !str_starts_with(ltrim($content, " \t\r\n"), '{')
        ) {
            return null;
        }

        $translations = [];

        foreach ($decoded as $key => $byLocale) {
            if (
                !is_array($byLocale)
                || (($byLocale !== []) && array_is_list($byLocale))
            ) {
                return null;
            }

            // PHP turns a key that reads as an integer into an integer, and a source text can be a number.
            $key = (string) $key;

            foreach ($byLocale as $locale => $value) {
                $locale = (string) $locale;

                if (is_string($value)) {
                    if ($this->unwritable($value)) {
                        return null;
                    }

                    $translations[$key][$locale] = $value;

                    continue;
                }

                if (!is_array($value)) {
                    return null;
                }

                foreach ($value as $form) {
                    if (
                        !is_string($form)
                        || $this->unwritable($form)
                    ) {
                        return null;
                    }
                }

                if (!array_is_list($value)) {
                    return null;
                }

                $translations[$key][$locale] = $value;
            }
        }

        return $translations;
    }

    /**
     * Tells whether a plan writes a translation for a plural entry.
     *
     * @param array<array-key, list<string>>            $expected The planned translations by key
     * @param array<string, array{plural: string|null}> $entries  The entries of the catalogue by key
     *
     * @return bool True when at least one planned entry is a plural entry
     */
    private function writesPluralEntry(array $expected, array $entries): bool
    {
        foreach (array_keys($expected) as $key) {
            if (($entries[(string) $key]['plural'] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether a translation holds a character the gettext tools cannot carry in a text.
     *
     * @param string $text The translation
     *
     * @return bool True for a NUL character or the context separator
     */
    private function unwritable(string $text): bool
    {
        return str_contains($text, "\0") || str_contains($text, "\x04");
    }

    /**
     * Reads the keys webtrees core translates itself from a template.
     *
     * @param string|null          $file      The path of the template, null for none
     * @param CatalogueToolchain   $toolchain The toolchain that reads it
     * @param CatalogueEntryReader $reader    The reader of its entries
     *
     * @return array<string, true> The keys
     *
     * @throws CatalogueToolException When the template cannot be read
     */
    private function readCoreOwned(?string $file, CatalogueToolchain $toolchain, CatalogueEntryReader $reader): array
    {
        if ($file === null) {
            return [];
        }

        return array_fill_keys(array_keys($reader->read($toolchain->normalise($file))['entries']), true);
    }

    /**
     * Reads the number of plural forms the Plural-Forms field of a catalogue header declares.
     *
     * @param string $header The header block as written
     *
     * @return int|null The declared number, or null when the header declares none
     */
    private function declaredForms(string $header): ?int
    {
        if (preg_match('/^"Plural-Forms:\s*nplurals\s*=\s*(\d+)/m', $header, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Tells whether a finished catalogue holds the entries of the original with only the planned translations
     * changed.
     *
     * @param array{header: string, entries: array<string, array{context: ?string, msgid: string, plural: ?string, forms: list<string>, fuzzy: bool, comments: list<string>, flags: list<string>}>} $finished The finished catalogue
     * @param array{header: string, entries: array<string, array{context: ?string, msgid: string, plural: ?string, forms: list<string>, fuzzy: bool, comments: list<string>, flags: list<string>}>} $original The catalogue before
     * @param array<string, list<string>>                                                                                                                                                           $expected The planned translations by key
     *
     * @return bool True when the entries and their translations are as planned
     */
    private function holdsExactly(array $finished, array $original, array $expected): bool
    {
        if (count($finished['entries']) !== count($original['entries'])) {
            return false;
        }

        foreach ($original['entries'] as $key => $entry) {
            if (!array_key_exists($key, $finished['entries'])) {
                return false;
            }

            $forms = $expected[$key] ?? $entry['forms'];

            if (
                ($finished['entries'][$key]['forms'] !== $forms)
                || ($finished['entries'][$key]['fuzzy'] !== $entry['fuzzy'])
                || ($finished['entries'][$key]['plural'] !== $entry['plural'])
            ) {
                return false;
            }
        }

        return true;
    }
}
