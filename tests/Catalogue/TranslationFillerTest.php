<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Catalogue;

use MagicSunday\Webtrees\ModuleBase\Catalogue\CatalogueBlockParser;
use MagicSunday\Webtrees\ModuleBase\Catalogue\CatalogueEntryReader;
use MagicSunday\Webtrees\ModuleBase\Catalogue\ParsedEntry;
use MagicSunday\Webtrees\ModuleBase\Catalogue\PrintableText;
use MagicSunday\Webtrees\ModuleBase\Catalogue\TranslationFiller;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Pins which entries of a catalogue get a translation from a translations file.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(TranslationFiller::class)]
#[UsesClass(CatalogueEntryReader::class)]
#[UsesClass(CatalogueBlockParser::class)]
#[UsesClass(ParsedEntry::class)]
#[UsesClass(PrintableText::class)]
final class TranslationFillerTest extends TestCase
{
    /**
     * Reads a catalogue made of the given entries.
     *
     * @param string $entries The entries after the header
     *
     * @return array{header: string, entries: array<string, array{context: ?string, msgid: string, plural: ?string, forms: list<string>, fuzzy: bool, comments: list<string>, flags: list<string>}>} The catalogue
     */
    private function catalogue(string $entries): array
    {
        return (new CatalogueEntryReader())->read("msgid \"\"\nmsgstr \"\"\n\n" . $entries);
    }

    /**
     * An empty singular entry and an empty plural entry get their strings, and the plural one gets as many forms as
     * the locale has.
     */
    #[Test]
    public function writesEmptyEntriesWithTheFormsOfTheLocale(): void
    {
        $plan = (new TranslationFiller())->plan(
            $this->catalogue(
                "msgid \"Hello\"\nmsgstr \"\"\n\n"
                . "msgid \"One\"\nmsgid_plural \"Many\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\nmsgstr[2] \"\"\n",
            ),
            [
                'Hello' => ['ru' => 'Привет'],
                'One'   => ['ru' => ['a', 'b', 'c']],
            ],
            'ru',
            3,
            [],
        );

        self::assertSame(2, $plan['written']);
        self::assertSame([], $plan['refused']);
        self::assertSame(['Привет'], $plan['expected']['Hello']);
        self::assertSame(['a', 'b', 'c'], $plan['expected']['One']);
        self::assertStringContainsString('msgstr[2] "c"', $plan['overlay']);
    }

    /**
     * A plural entry with the wrong number of forms is refused and named, and the others are still written.
     */
    #[Test]
    public function refusesAPluralEntryWhoseFormCountIsWrong(): void
    {
        $plan = (new TranslationFiller())->plan(
            $this->catalogue(
                "msgid \"Hello\"\nmsgstr \"\"\n\n"
                . "msgid \"One\"\nmsgid_plural \"Many\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\nmsgstr[2] \"\"\n",
            ),
            [
                'Hello' => ['ru' => 'Привет'],
                'One'   => ['ru' => ['a', 'b']],
            ],
            'ru',
            3,
            [],
        );

        self::assertSame(1, $plan['written']);
        self::assertCount(1, $plan['refused']);
        self::assertStringContainsString('needs 3 forms and the translations file lists 2', $plan['refused'][0]);
        self::assertArrayNotHasKey('One', $plan['expected']);
    }

    /**
     * A singular entry given a list and a plural entry given a string are both refused.
     */
    #[Test]
    public function refusesAStringForAPluralEntryAndAListForASingularEntry(): void
    {
        $plan = (new TranslationFiller())->plan(
            $this->catalogue(
                "msgid \"Hello\"\nmsgstr \"\"\n\n"
                . "msgid \"One\"\nmsgid_plural \"Many\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n",
            ),
            [
                'Hello' => ['de' => ['a']],
                'One'   => ['de' => 'eins'],
            ],
            'de',
            2,
            [],
        );

        self::assertSame(0, $plan['written']);
        self::assertCount(2, $plan['refused']);
    }

    /**
     * A translated entry, a fuzzy entry, a core-owned entry and an entry the file holds nothing for are left alone.
     */
    #[Test]
    public function leavesTranslatedFuzzyCoreOwnedAndUnlistedEntriesAlone(): void
    {
        $plan = (new TranslationFiller())->plan(
            $this->catalogue(
                "msgid \"Done\"\nmsgstr \"Fertig\"\n\n"
                . "#, fuzzy\nmsgid \"Fuzzy\"\nmsgstr \"\"\n\n"
                . "msgid \"Core\"\nmsgstr \"\"\n\n"
                . "msgid \"Unlisted\"\nmsgstr \"\"\n\n"
                . "msgid \"Other\"\nmsgstr \"\"\n",
            ),
            [
                'Done'  => ['de' => 'neu'],
                'Fuzzy' => ['de' => 'unscharf'],
                'Core'  => ['de' => 'Kern'],
                'Other' => ['fr' => 'autre'],
            ],
            'de',
            2,
            ['Core' => true],
        );

        self::assertSame(0, $plan['written']);
        self::assertSame([], $plan['refused']);
        self::assertSame('', $plan['overlay']);
    }

    /**
     * A list that is too long is refused too, and one with an empty form next to a form with text is refused.
     */
    #[Test]
    public function refusesAListThatIsTooLongAndAListWithAnEmptyForm(): void
    {
        $plan = (new TranslationFiller())->plan(
            $this->catalogue(
                "msgid \"One\"\nmsgid_plural \"Many\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n\n"
                . "msgid \"Two\"\nmsgid_plural \"Twos\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n",
            ),
            [
                'One' => ['de' => ['a', 'b', 'c']],
                'Two' => ['de' => ['a', '']],
            ],
            'de',
            2,
            [],
        );

        self::assertSame(0, $plan['written']);
        self::assertCount(2, $plan['refused']);
        self::assertStringContainsString('"One" needs 2 forms and the translations file lists 3', $plan['refused'][0]);
        self::assertStringContainsString('"Two" lists an empty form', $plan['refused'][1]);
    }

    /**
     * An entry with a context is found by the key of the context and the source text.
     */
    #[Test]
    public function findsAnEntryWithAContextByItsKey(): void
    {
        $plan = (new TranslationFiller())->plan(
            $this->catalogue("msgctxt \"menu\"\nmsgid \"File\"\nmsgstr \"\"\n"),
            ["menu\x04File" => ['de' => 'Datei']],
            'de',
            2,
            [],
        );

        self::assertSame(1, $plan['written']);
        self::assertStringContainsString("msgctxt \"menu\"\nmsgid \"File\"\nmsgstr \"Datei\"", $plan['overlay']);
    }
}
