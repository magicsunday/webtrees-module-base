<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Testing;

use Fisharebest\Localization\Locale;
use Fisharebest\Localization\Translation;
use Fisharebest\Localization\Translator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function count;
use function explode;
use function file_get_contents;
use function implode;
use function in_array;
use function is_dir;
use function is_file;
use function ksort;
use function preg_match;
use function preg_match_all;
use function rtrim;
use function scandir;
use function sort;
use function sprintf;
use function str_contains;
use function str_replace;
use function stripcslashes;
use function strpos;
use function substr;

use const PREG_SET_ORDER;

/**
 * Locks the structure of the shipped translation catalogues.
 *
 * webtrees merges the compiled catalogue of a module over its own, so an entry that
 * webtrees cannot use does not just degrade the module that ships it. A plural entry
 * with the wrong number of forms makes webtrees fall back to English. An entry with an
 * empty form behind a filled first form renders an empty string for every number that
 * selects that form. Because the entry replaces the webtrees translation of the same
 * text, the damage reaches pages that have nothing to do with this module.
 *
 * The expected number of forms is taken from the plural rule class webtrees itself
 * uses for the locale, never from the PO header, which webtrees does not read.
 *
 * A module extends this case in its own test suite and names the directory that holds
 * its catalogues, one subdirectory per locale with a messages.po and its compiled
 * messages.mo. Most checks then run once per shipped locale. The others look at the
 * catalogues of all locales at once.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
abstract class AbstractCatalogueStructureTestCase extends TestCase
{
    /**
     * Separator gettext uses between the singular and the plural msgid in a key and
     * between the plural forms of one translation.
     */
    private const string PLURAL_SEPARATOR = "\x00";

    /**
     * Highest number rendered for every plural entry. It covers every residue class
     * of the plural rules shipped for the locales, including the teens that the Slavic
     * rules treat separately.
     */
    private const int HIGHEST_NUMBER = 200;

    /**
     * Provides every locale the module ships a catalogue for.
     *
     * @return array<string, array{string}> The data rows, one per locale, keyed by the locale
     */
    public static function shippedLocales(): array
    {
        $locales = [];

        foreach (self::localesWithFile('messages.po') as $locale) {
            $locales[$locale] = [$locale];
        }

        return $locales;
    }

    /**
     * Every locale has both its source and its compiled catalogue. A locale that lost its
     * source drops out of the data provider and would no longer be checked. An orphaned
     * compiled file would ship unchecked. An empty provider would check nothing.
     */
    #[Test]
    public function everyLocaleHasItsSourceAndItsCompiledCatalogue(): void
    {
        $sources = self::localesWithFile('messages.po');

        self::assertNotSame([], $sources);
        self::assertSame($sources, self::localesWithFile('messages.mo'));
    }

    /**
     * The PO header of a locale declares the plural rule the translation tools use. The
     * declaration must be complete. A rule split by other header lines is read wrongly
     * by tools that evaluate it. Its form count must also agree with the rule webtrees
     * applies. Otherwise a translator is asked for the wrong number of forms and the
     * file is wrong from the start.
     *
     * @param string $locale The name of the locale directory
     */
    #[Test]
    #[DataProvider('shippedLocales')]
    public function headerDeclaresThePluralRuleOfWebtrees(string $locale): void
    {
        $found = preg_match(
            '/^Plural-Forms: nplurals=(\d+); plural=[^\n]+;$/m',
            $this->headerText($locale),
            $matches,
        );

        self::assertSame(
            1,
            $found,
            sprintf('%s: the header carries no complete Plural-Forms line', $locale),
        );

        self::assertSame(
            $this->pluralRuleFormCount($locale),
            (int) $matches[1],
            sprintf('%s: nplurals in the header differs from the plural rule of webtrees', $locale),
        );
    }

    /**
     * Every plural entry of the compiled catalogue, the file webtrees actually loads,
     * must carry exactly as many forms as the plural rule of the locale selects from.
     * With a different count webtrees ignores the entry and shows English. The entry
     * still hides the webtrees translation of the same text.
     *
     * @param string $locale The name of the locale directory
     */
    #[Test]
    #[DataProvider('shippedLocales')]
    public function pluralEntriesCarryExactlyTheFormsOfThePluralRule(string $locale): void
    {
        $expected  = $this->pluralRuleFormCount($locale);
        $offenders = [];

        foreach ($this->pluralEntries($this->compiledCatalogue($locale)) as $msgid => $forms) {
            if (count($forms) !== $expected) {
                $offenders[] = sprintf('%s (%d forms)', $msgid, count($forms));
            }
        }

        self::assertSame(
            [],
            $offenders,
            sprintf(
                '%s needs %d plural forms per entry: %s',
                $locale,
                $expected,
                implode(' | ', $offenders),
            ),
        );
    }

    /**
     * The tests that scan the plural entries of the compiled catalogues pass on an empty
     * scan. At least one compiled catalogue therefore has to carry a plural entry. The
     * test then fails loudly when the reader stops recognising plural keys. A module that
     * declares no plural entries is checked for their absence instead, so one that is
     * added later fails until the declaration is removed.
     */
    #[Test]
    public function compiledCataloguesCarryPluralEntries(): void
    {
        $found = 0;

        foreach (self::localesWithFile('messages.mo') as $locale) {
            $found += count($this->pluralEntries($this->compiledCatalogue($locale)));
        }

        if (!static::shipsPluralEntries()) {
            self::assertSame(
                0,
                $found,
                'The module declares no plural entries, but a compiled catalogue carries one',
            );

            return;
        }

        self::assertGreaterThan(
            0,
            $found,
            'No compiled catalogue carries a plural entry, so the plural checks would scan nothing',
        );
    }

    /**
     * The compiler leaves out an entry without any translation, so the compiled catalogue
     * cannot tell how many slots such an entry has in the source. The slot count can
     * therefore only be checked in the source file.
     *
     * @param string $locale The name of the locale directory
     */
    #[Test]
    #[DataProvider('shippedLocales')]
    public function sourcePluralEntriesHaveTheSlotsOfThePluralRule(string $locale): void
    {
        $expected = $this->pluralRuleFormCount($locale);
        $source   = $this->poSource($locale);

        if (!static::shipsPluralEntries()) {
            self::assertSame(
                0,
                preg_match_all('/^msgid_plural /m', $source),
                sprintf('%s: the module declares no plural entries, but the source catalogue has one', $locale),
            );

            return;
        }

        $pattern = '/^msgid_plural (.*)\n(?:".*\n)*((?:msgstr\[\d+\] .*\n(?:".*\n)*)+)/m';
        $found   = preg_match_all($pattern, $source, $entries, PREG_SET_ORDER);

        self::assertGreaterThan(
            0,
            $found,
            sprintf('%s: the source catalogue has no plural entry', $locale),
        );
        self::assertSame(
            preg_match_all('/^msgid_plural /m', $source),
            $found,
            sprintf('%s: a plural entry of the source catalogue was not read', $locale),
        );

        $offenders = [];

        foreach ($entries as $entry) {
            $slots = preg_match_all('/^msgstr\[\d+\]/m', $entry[2]);

            if ($slots !== $expected) {
                $offenders[] = sprintf('%s (%d slots)', $entry[1], $slots);
            }
        }

        self::assertSame(
            [],
            $offenders,
            sprintf(
                '%s needs %d plural slots per entry: %s',
                $locale,
                $expected,
                implode(' | ', $offenders),
            ),
        );
    }

    /**
     * An empty plural form behind a filled first form is kept by the compiler, and
     * webtrees returns it unchanged. The number that selects it would render as nothing.
     *
     * @param string $locale The name of the locale directory
     */
    #[Test]
    #[DataProvider('shippedLocales')]
    public function noPluralFormIsEmpty(string $locale): void
    {
        $offenders = [];

        foreach ($this->pluralEntries($this->compiledCatalogue($locale)) as $msgid => $forms) {
            if (in_array('', $forms, true)) {
                $offenders[] = $msgid;
            }
        }

        self::assertSame(
            [],
            $offenders,
            sprintf(
                '%s has plural entries with an empty form: %s',
                $locale,
                implode(' | ', $offenders),
            ),
        );
    }

    /**
     * The compiled catalogue is committed next to its source. A catalogue compiled
     * from an older source would let the checks above pass on stale data. The PO
     * reader and the compiled catalogue reader return the entries in different order,
     * which carries no meaning. The PO reader keeps an entry marked fuzzy in the
     * source, while the compiler leaves it out of the compiled file. The compiler also
     * leaves out a plural entry whose first form is empty. Such an entry shows up here
     * as a mismatch until it is resolved.
     *
     * @param string $locale The name of the locale directory
     */
    #[Test]
    #[DataProvider('shippedLocales')]
    public function compiledCatalogueMatchesTheSourceCatalogue(string $locale): void
    {
        $source   = (new Translation($this->poFile($locale)))->asArray();
        $compiled = $this->compiledCatalogue($locale);

        ksort($source);
        ksort($compiled);

        self::assertSame(
            $source,
            $compiled,
            sprintf(
                '%s: messages.mo is out of date or the source holds a fuzzy entry or a plural entry with an empty'
                . ' first form. Compile messages.mo again from messages.po and resolve a fuzzy'
                . ' entry or an empty first form in the source.',
                $locale,
            ),
        );
    }

    /**
     * Renders every plural entry the way webtrees does, for every number up to the
     * highest one. An empty form behind a filled first form shows up as an empty result
     * for the numbers that select it. A wrong form count is not seen here, because
     * webtrees then shows the English text. The form count test above covers that case.
     *
     * @param string $locale The name of the locale directory
     */
    #[Test]
    #[DataProvider('shippedLocales')]
    public function everyPluralEntryRendersForEveryNumber(string $locale): void
    {
        $catalogue  = $this->compiledCatalogue($locale);
        $translator = new Translator($catalogue, Locale::create($locale)->pluralRule());
        $failures   = [];

        foreach (array_keys($this->pluralEntries($catalogue)) as $key) {
            [$singular, $plural] = $this->sourceTexts($key);

            for ($number = 0; $number <= self::HIGHEST_NUMBER; ++$number) {
                if ($translator->translatePlural($singular, $plural, $number) === '') {
                    $failures[] = sprintf('%s (n=%d)', $singular, $number);

                    break;
                }
            }
        }

        self::assertSame(
            [],
            $failures,
            sprintf(
                '%s renders nothing for: %s',
                $locale,
                implode(' | ', $failures),
            ),
        );
    }

    /**
     * A form that drops or invents a placeholder breaks the sentence it is formatted
     * into. Every form must use the placeholders of the singular or of the plural
     * source text. Only the string and integer placeholders (`%s`, `%d`, numbered ones
     * included) are compared, because the source texts use no other conversion. Numbered
     * placeholders may change their position, unnumbered ones may not.
     *
     * @param string $locale The name of the locale directory
     */
    #[Test]
    #[DataProvider('shippedLocales')]
    public function pluralFormsKeepThePlaceholdersOfTheSource(string $locale): void
    {
        $offenders = [];

        foreach ($this->pluralEntries($this->compiledCatalogue($locale)) as $key => $forms) {
            [$singular, $plural] = $this->sourceTexts($key);

            $allowed = [
                $this->placeholders($singular),
                $this->placeholders($plural),
            ];

            foreach ($forms as $index => $form) {
                if (!in_array($this->placeholders($form), $allowed, true)) {
                    $offenders[] = sprintf('%s [%d]', $singular, $index);
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            sprintf(
                '%s has plural forms with other placeholders than the source: %s',
                $locale,
                implode(' | ', $offenders),
            ),
        );
    }

    /**
     * Returns the header of the source catalogue of a locale as plain text, with the
     * quoted lines of the header joined and unescaped the way a PO reader sees them.
     *
     * @param string $locale The name of the locale directory
     *
     * @return string The header text with the quoted lines joined and unescaped
     */
    private function headerText(string $locale): string
    {
        $source  = $this->poSource($locale);
        $pattern = '/^msgid ""\nmsgstr ""\n((?:"(?:[^"\\\\]|\\\\.)*"\n)+)/m';
        $found   = preg_match($pattern, $source, $block);

        self::assertSame(
            1,
            $found,
            sprintf('%s: the catalogue carries no header', $locale),
        );

        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $block[1], $lines);

        return stripcslashes(implode('', $lines[1]));
    }

    /**
     * Lists the locales that have the named catalogue file, sorted.
     *
     * @param string $file The name of the catalogue file
     *
     * @return list<string> The names of the locale directories
     */
    private static function localesWithFile(string $file): array
    {
        $directory = static::languageDirectory();
        $entries   = is_dir($directory) ? scandir($directory) : false;
        $found     = [];

        foreach ($entries === false ? [] : $entries as $entry) {
            if (
                ($entry !== '.')
                && ($entry !== '..')
                && is_file($directory . '/' . $entry . '/' . $file)
            ) {
                $found[] = $entry;
            }
        }

        sort($found);

        return $found;
    }

    /**
     * Returns the plural entries of a catalogue, keyed by the singular and plural
     * source text joined by the plural separator, with the forms split into a list.
     *
     * @param array<string, string> $catalogue The catalogue as the translation reader returns it
     *
     * @return array<string, list<string>> The forms of every plural entry, keyed by its source texts
     */
    private function pluralEntries(array $catalogue): array
    {
        $entries = [];

        foreach ($catalogue as $key => $translation) {
            if (str_contains((string) $key, self::PLURAL_SEPARATOR)) {
                $entries[(string) $key] = explode(self::PLURAL_SEPARATOR, $translation);
            }
        }

        return $entries;
    }

    /**
     * Returns the placeholders of a text. Unnumbered placeholders keep their order,
     * because the formatter binds them to its arguments one after the other. Numbered
     * placeholders name their argument, so their position in the sentence does not
     * matter and they are sorted. A doubled percent sign is a literal one and not a
     * placeholder.
     *
     * @param string $text The text to read
     *
     * @return list<string> The unnumbered placeholders in text order, then the numbered ones sorted
     */
    private function placeholders(string $text): array
    {
        $text = str_replace('%%', '', $text);

        preg_match_all('/%(?:\d+\$)?[sd]/', $text, $matches);

        $unnumbered = [];
        $numbered   = [];

        foreach ($matches[0] as $placeholder) {
            if (str_contains($placeholder, '$')) {
                $numbered[] = $placeholder;

                continue;
            }

            $unnumbered[] = $placeholder;
        }

        sort($numbered);

        return [...$unnumbered, ...$numbered];
    }

    /**
     * Reads the compiled catalogue webtrees loads for a locale.
     *
     * @param string $locale The name of the locale directory
     *
     * @return array<string, string> The entries keyed by their source texts
     */
    private function compiledCatalogue(string $locale): array
    {
        $moFile = static::languageDirectory() . '/' . $locale . '/messages.mo';

        self::assertTrue(
            is_file($moFile),
            sprintf('%s: messages.mo is missing', $locale),
        );

        $translations = (new Translation($moFile))->asArray();

        /** @var array<string, string> $translations */
        return $translations;
    }

    /**
     * Splits the key of a plural entry into the singular and the plural source text.
     *
     * @param string $key The key of a plural entry
     *
     * @return array{string, string} The singular and the plural source text
     */
    private function sourceTexts(string $key): array
    {
        $position = strpos($key, self::PLURAL_SEPARATOR);

        self::assertIsInt(
            $position,
            'A plural entry key joins both source texts by the plural separator',
        );

        return [substr($key, 0, $position), substr($key, $position + 1)];
    }

    /**
     * Returns the number of plural forms webtrees selects from for a locale.
     *
     * @param string $locale The name of the locale directory
     *
     * @return int The number of plural forms of the plural rule of the locale
     */
    private function pluralRuleFormCount(string $locale): int
    {
        return Locale::create($locale)->pluralRule()->plurals();
    }

    /**
     * Returns the path of the source catalogue of a locale.
     *
     * @param string $locale The name of the locale directory
     *
     * @return string The path of the source catalogue
     */
    private function poFile(string $locale): string
    {
        return static::languageDirectory() . '/' . $locale . '/messages.po';
    }

    /**
     * Returns the raw text of the source catalogue of a locale. Windows line endings are
     * turned into plain ones and a final line break is ensured. The line anchored
     * patterns then match on every checkout and for a file that ends without a line
     * break.
     *
     * @param string $locale The name of the locale directory
     *
     * @return string The source text with plain line endings
     */
    private function poSource(string $locale): string
    {
        $source = file_get_contents($this->poFile($locale));

        self::assertIsString(
            $source,
            sprintf('%s: the source catalogue cannot be read', $locale),
        );

        return rtrim(str_replace("\r\n", "\n", $source), "\n") . "\n";
    }

    /**
     * Returns the directory that holds the catalogues of all locales, one subdirectory per
     * locale with a messages.po and its compiled messages.mo.
     *
     * @return string The path of the catalogue directory
     */
    abstract protected static function languageDirectory(): string;

    /**
     * Tells whether the catalogues of the module ship plural entries. A module without any
     * plural string overrides this and returns false, which turns the anchor that demands
     * a plural entry into a check that none exists.
     *
     * @return bool True when at least one plural entry is expected
     */
    protected static function shipsPluralEntries(): bool
    {
        return true;
    }
}
