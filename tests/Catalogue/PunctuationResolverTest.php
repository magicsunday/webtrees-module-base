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
use MagicSunday\Webtrees\ModuleBase\Catalogue\CatalogueLayoutException;
use MagicSunday\Webtrees\ModuleBase\Catalogue\ParsedEntry;
use MagicSunday\Webtrees\ModuleBase\Catalogue\PrintableText;
use MagicSunday\Webtrees\ModuleBase\Catalogue\PunctuationResolver;
use MagicSunday\Webtrees\ModuleBase\Catalogue\Resolution;
use MagicSunday\Webtrees\ModuleBase\Catalogue\ResolvedEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function hrtime;
use function implode;
use function str_repeat;

/**
 * Pins which fuzzy entries the resolver clears and which it leaves for a person. The
 * catalogues are written the way msgmerge writes them with its previous-msgid option.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(PunctuationResolver::class)]
#[UsesClass(CatalogueBlockParser::class)]
#[UsesClass(CatalogueLayoutException::class)]
#[UsesClass(ParsedEntry::class)]
#[UsesClass(ResolvedEntry::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(PrintableText::class)]
final class PunctuationResolverTest extends TestCase
{
    /**
     * The header every catalogue of these tests starts with.
     */
    private const string HEADER = <<<'PO'
        msgid ""
        msgstr ""
        "Content-Type: text/plain; charset=UTF-8\n"
        "Plural-Forms: nplurals=2; plural=(n != 1);\n"
        PO;

    /**
     * Joins the header and the given entries the way a catalogue file lays them out.
     *
     * @param string ...$entries The entries, each without a trailing blank line
     *
     * @return string The catalogue text
     */
    private function catalogue(string ...$entries): string
    {
        return self::HEADER . "\n\n" . implode("\n\n", $entries) . "\n";
    }

    /**
     * Provides a source text change that is only punctuation, with the translation as it
     * stood before and the one expected after. The rows cover each separator pair in both
     * directions, changes of spaces only and a translation that lacks the separator.
     *
     * @return array<string, array{string, string, string, string}> The rows, keyed by the change, each with the old
     *                                                              source text, the new one, the old translation and
     *                                                              the expected translation
     */
    public static function punctuationChanges(): array
    {
        return [
            'em dash to middle dot'       => ['Births — per year', 'Births · per year', 'Geburten — pro Jahr', 'Geburten · pro Jahr'],
            'middle dot to em dash'       => ['Births · per year', 'Births — per year', 'Geburten · pro Jahr', 'Geburten — pro Jahr'],
            'en dash to middle dot'       => ['Births – per year', 'Births · per year', 'Geburten – pro Jahr', 'Geburten · pro Jahr'],
            'em dash to en dash'          => ['Births — per year', 'Births – per year', 'Geburten — pro Jahr', 'Geburten – pro Jahr'],
            'spaced hyphen to em dash'    => ['Births - per year', 'Births — per year', 'Geburten - pro Jahr', 'Geburten — pro Jahr'],
            'spaced hyphen to en dash'    => ['Births - per year', 'Births – per year', 'Geburten - pro Jahr', 'Geburten – pro Jahr'],
            'spaced hyphen to dot'        => ['Births - per year', 'Births · per year', 'Geburten - pro Jahr', 'Geburten · pro Jahr'],
            'doubled space to one space'  => ['Births  per year', 'Births per year', 'Geburten pro Jahr', 'Geburten pro Jahr'],
            'translation without it'      => ['Births — per year', 'Births · per year', 'Geburten pro Jahr', 'Geburten pro Jahr'],
            'trailing space removed'      => ['Births per year ', 'Births per year', 'Geburten pro Jahr', 'Geburten pro Jahr'],
            'leading space removed'       => [' Births per year', 'Births per year', 'Geburten pro Jahr', 'Geburten pro Jahr'],
            'translation has the new one' => ['Births - per year', 'Births · per year', 'Geburten · pro Jahr', 'Geburten · pro Jahr'],
            'escaped backslash before t'  => ['A - B', 'A · B', 'X\\\\t-\\\\tY', 'X\\\\t-\\\\tY'],
            'hyphen inside a word'        => ['Born - died', 'Born · died', 'Rhein-Main gestorben', 'Rhein-Main gestorben'],
            'trailing space added'        => ['Name:', 'Name: ', 'Name:', 'Name: '],
            'translation follows old gap' => ['Place ', 'Place', 'Ort ', 'Ort'],
            'leading space follows old'   => [' Place', 'Place', ' Ort', 'Ort'],
            'dash next to a kept dot'     => ['A - B · C', 'A — B · C', 'X - Y · Z', 'X — Y · Z'],
            'dash to a dot already used'  => ['A - B · C', 'A · B · C', 'X - Y · Z', 'X · Y · Z'],
            'kept trailing space'         => ['Label - chart ', 'Label · chart ', 'Etikett - Diagramm', 'Etikett · Diagramm'],
            'kept leading space'          => [' Label - chart', ' Label · chart', 'Etikett - Diagramm', 'Etikett · Diagramm'],
        ];
    }

    /**
     * A fuzzy entry whose source text only changed in a separator or in spaces is resolved with the same swap applied to its translation.
     *
     * @param string $oldMsgid  The source text before the change
     * @param string $newMsgid  The source text after the change
     * @param string $oldMsgstr The translation before the change
     * @param string $newMsgstr The translation expected after the change
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    #[DataProvider('punctuationChanges')]
    public function resolvesAChangeThatIsOnlyPunctuation(
        string $oldMsgid,
        string $newMsgid,
        string $oldMsgstr,
        string $newMsgstr,
    ): void {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#| msgid \"$oldMsgid\"\nmsgid \"$newMsgid\"\nmsgstr \"$oldMsgstr\""),
        );

        self::assertSame(1, $resolution->resolved);
        self::assertSame([], $resolution->unresolved);
        self::assertSame(
            $this->catalogue("msgid \"$newMsgid\"\nmsgstr \"$newMsgstr\""),
            $resolution->overlay,
        );
        self::assertSame(
            ($oldMsgstr === $newMsgstr) ? [] : [$newMsgid],
            $resolution->rewritten,
        );
    }

    /**
     * A separator that is the same in the old and the new source text is not swapped in the translation when only spaces changed.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function keepsASeparatorThatDidNotChange(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            self::catalogue(
                "#, fuzzy\n#| msgid \"Births · per year \"\nmsgid \"Births · per year\"\nmsgstr \"Geburten · pro Jahr\"",
            ),
        );

        self::assertSame(1, $resolution->resolved);
        self::assertStringContainsString('msgstr "Geburten · pro Jahr"', $resolution->overlay);
    }

    /**
     * Provides a source text in which a separator changed at some of its places only, in which
     * several kinds of separator changed, or in which the separators changed their order, so that
     * a swap in the translation cannot tell which of its separators is meant. A separator mark with
     * no space next to it at the start or the end of a text belongs here as well.
     *
     * @return array<string, array{string, string, string}> The rows, keyed by the change, each with the old source
     *                                                      text, the new one and the translation
     */
    public static function ambiguousSeparatorChanges(): array
    {
        return [
            'one of two em dashes'                   => ['A — B — C', 'A · B — C', 'X — Y — Z'],
            'one of two middle dots'                 => ['A · B · C', 'A — B · C', 'X · Y · Z'],
            'second of two spaced dash'              => ['A - B - C', 'A - B — C', 'X - Y - Z'],
            'translation has another dash'           => ['Foo - Bar', 'Foo · Bar', 'Foo – Bar'],
            'old and another dash together'          => ['A - B', 'A · B', 'X - Y – Z'],
            'translation holds it less often'        => ['A - B - C', 'A · B · C', 'X - Y'],
            'overlapping pair in it left over'       => ['A - B - C', 'A – B – C', 'X - - Y'],
            'overlapping separators in it'           => ['A - B', 'A · B', 'x - - y'],
            'adjacent separators reordered'          => ['A — · B', 'A · — B', 'X — · Y'],
            'adjacent spaced dash and dot'           => ['A - · B', 'A · - B', 'X - Y'],
            'adjacent dot and spaced dash'           => ['A · - B', 'A - · B', 'X · Y'],
            'translation of spaces only'             => ['x - y', 'x · y', ' '],
            'source of spaces only'                  => [' ', '  ', ' '],
            'source of spaces, translation has text' => [' ', '  ', ' X '],
            'old next to the new one'                => ['A - B', 'A · B', 'X - Y · Z'],
            'translation holds it more often'        => ['Born - Died', 'Born · Died', 'Geboren - Gestorben - tot'],
            'edge space matches neither'             => ['Place ', 'Place', 'Ort  '],
            'ends in a no-break space'               => ['Place ', 'Place', "Ort\u{A0}"],
            'ends in a no-break space and a space'   => ['Place ', 'Place', "Ort\u{A0} "],
            'ends in an escaped tab'                 => ['Place ', 'Place', 'Ort\t'],
            'two kinds changed at once'              => ['A - B · C', 'A — B – C', 'X - Y · Z'],
            'two vanished one added'                 => ['A - B · C', 'A — B — C', 'X - Y · Z'],
            'one vanished two added'                 => ['A - B - C', 'A — B · C', 'X - Y - Z'],
            'order of two kinds swapped'             => ['A — B · C', 'A · B — C', 'X — Y · Z'],
            'swap and move together'                 => ['A - B · C', 'A · B — C', 'X - Y · Z'],
            'dash without spaces in it'              => ['Births — per year', 'Births · per year', 'Geburten—pro Jahr'],
            'dash at the end without space'          => ['Marriages — ', 'Marriages · ', 'Heiraten —'],
            'dot without spaces in it'               => ['Births · per year', 'Births — per year', 'Geburten·pro Jahr'],
            'kept dot translated as a dash'          => ['Born - died · details', 'Born — died · details', 'Geboren und gestorben - Details'],
            'translation reverses the kinds'         => ['Born - died · details', 'Born — died · details', 'Geboren · gestorben - Details'],
            'bare marks swapped at both edges'       => [' — A ·', '· A — ', ' — B ·'],
            'bare dash at the start'                 => ['— A', '· A', '— B'],
            'bare dot at the end'                    => ['A ·', 'A —', 'B ·'],
            'hyphen alone at the end'                => ['Name - ', 'Name – ', 'Name -'],
            'hyphen with a space on one side'        => ['Von A - B', 'Von A – B', 'Von A -B'],
            'hyphen with a space after it only'      => ['Births - per year', 'Births – per year', 'Geburten- pro Jahr'],
            'hyphen at the start of the text'        => ['Births - per year', 'Births – per year', '-Geburten pro Jahr'],
            'hyphen at the end of the text'          => ['Births - per year', 'Births – per year', 'Geburten pro Jahr-'],
            'old one and a hyphen at the end'        => ['A - B', 'A · B', 'X - Y -'],
            'old one and a hyphen at the start'      => ['A - B', 'A · B', '- X - Y'],
            'dash with a no-break space'             => ['Help - Overview', 'Help · Overview', "Hilfe\u{A0}– Übersicht"],
            'hyphen between tab escapes'             => ['A - B', 'A · B', 'X\\t-\\tY'],
            'hyphen between line feed escapes'       => ['A - B', 'A · B', 'X\\n-\\nY'],
            'dash between form feed escapes'         => ['A - B', 'A — B', 'X\\f–\\fY'],
            'dash with a tab escape'                 => ['Help - Overview', 'Help — Overview', 'Hilfe –\\tÜbersicht'],
            'dash with a line separator'             => ['Help - Overview', 'Help — Overview', "Hilfe –\u{2028}Übersicht"],
            'dash with a no-break space behind'      => ['Help - Overview', 'Help · Overview', "Hilfe –\u{A0}Übersicht"],
        ];
    }

    /**
     * A fuzzy entry in which only some of the equal separators changed, in which several kinds of separator
     * changed or in which the separators changed their order, stays fuzzy and is reported, because the translation cannot be corrected without guessing which
     * of its separators belongs to the change.
     *
     * @param string $oldMsgid The source text before the change
     * @param string $newMsgid The source text after the change
     * @param string $msgstr   The translation
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    #[DataProvider('ambiguousSeparatorChanges')]
    public function reportsAnEntryWhoseSeparatorSwapIsAmbiguous(
        string $oldMsgid,
        string $newMsgid,
        string $msgstr,
    ): void {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#| msgid \"$oldMsgid\"\nmsgid \"$newMsgid\"\nmsgstr \"$msgstr\""),
        );

        self::assertSame(0, $resolution->resolved);
        self::assertSame([$newMsgid], $resolution->unresolved);
    }

    /**
     * A separator that changed at every place is swapped at every place of the translation, also when the
     * source text holds another separator that stayed.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function swapsEverySeparatorOfAKindThatChangedEverywhere(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue(
                "#, fuzzy\n#| msgid \"A — B — C · D\"\nmsgid \"A · B · C · D\"\nmsgstr \"W — X — Y · Z\"",
            ),
        );

        self::assertSame(1, $resolution->resolved);
        self::assertStringContainsString('msgstr "W · X · Y · Z"', $resolution->overlay);
    }

    /**
     * Two entries with the same source text under different contexts are told apart in the lists of entries a person has
     * to look at.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function namesTheContextOfAnEntryInTheLists(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue(
                "#, fuzzy\n#| msgid \"Deaths per decade\"\nmsgctxt \"chart\"\nmsgid \"Deaths per century\"\nmsgstr \"A\"",
                "#, fuzzy\n#| msgid \"Deaths per decade\"\nmsgctxt \"table\"\nmsgid \"Deaths per century\"\nmsgstr \"B\"",
                "#, fuzzy\n#| msgctxt \"chart\"\n#| msgid \"Place \"\nmsgctxt \"chart\"\nmsgid \"Place\"\nmsgstr \"Ort \"",
            ),
        );

        self::assertSame(['chart|Deaths per century', 'table|Deaths per century'], $resolution->unresolved);
        self::assertSame(['chart|Place'], $resolution->rewritten);
    }

    /**
     * A very long run of no-break spaces in a translation does not stall the check of its trailing space.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function readsALongRunOfNoBreakSpacesInLinearTime(): void
    {
        $translation = 'A' . str_repeat("\u{A0}", 60000) . 'b';
        $catalogue   = $this->catalogue("#, fuzzy\n#| msgid \"Name:\"\nmsgid \"Name: \"\nmsgstr \"$translation\"");

        $start      = hrtime(true);
        $resolution = (new PunctuationResolver())->resolve($catalogue);
        $seconds    = (hrtime(true) - $start) / 1_000_000_000;

        self::assertSame(1, $resolution->resolved);
        self::assertLessThan(1.0, $seconds);
    }

    /**
     * A fuzzy entry whose source text changed in a word stays fuzzy and is reported.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function leavesAChangeOfWordsToAPerson(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#| msgid \"Deaths per decade\"\nmsgid \"Deaths per century\"\nmsgstr \"Tode pro Jahrzehnt\""),
        );

        self::assertSame(0, $resolution->resolved);
        self::assertSame(['Deaths per century'], $resolution->unresolved);
        self::assertSame('', $resolution->overlay);
    }

    /**
     * A translation keeps its words, the resolver applies no vocabulary rule.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function doesNotRewriteTheGermanWordDecade(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#| msgid \"Births — per decade\"\nmsgid \"Births · per decade\"\nmsgstr \"Geburten — pro Dekade\""),
        );

        self::assertStringContainsString('msgstr "Geburten · pro Dekade"', $resolution->overlay);
    }

    /**
     * An entry without the fuzzy flag is neither resolved nor reported.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function ignoresEntriesThatAreNotFuzzy(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#: src/A.php:10\nmsgid \"Births · per year\"\nmsgstr \"Geburten · pro Jahr\""),
        );

        self::assertSame(0, $resolution->resolved);
        self::assertSame([], $resolution->unresolved);
        self::assertSame('', $resolution->overlay);
    }

    /**
     * An obsolete entry has no source text of its own, is not reported and does not stop the entries after it from being read.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function ignoresAnObsoleteEntryEvenWhenItCarriesTheFuzzyFlag(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            self::catalogue(
                "#, fuzzy\n#~ msgid \"Gone — x\"\n#~ msgstr \"Weg — x\"",
                "#, fuzzy\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\"",
            ),
        );

        self::assertSame(1, $resolution->resolved);
        self::assertSame([], $resolution->unresolved);
    }

    /**
     * The fuzzy flag is found on a flag line that carries other flags.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function readsTheFuzzyFlagFromAMixedFlagLine(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, php-format, fuzzy\n#| msgid \"%s — x\"\nmsgid \"%s · x\"\nmsgstr \"%s — y\""),
        );

        self::assertSame(1, $resolution->resolved);
        self::assertStringContainsString('msgstr "%s · y"', $resolution->overlay);
    }

    /**
     * A flag whose value holds a space is read like any other flag and does not make the catalogue refused.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function readsTheRangeFlagWithItsOwnSpace(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue(
                "#, range: 0..1\nmsgid \"One\"\nmsgstr \"Eins\"",
                "#, fuzzy, range: -5..10\n#| msgid \"%s — x\"\nmsgid \"%s · x\"\nmsgstr \"%s — y\"",
            ),
        );

        self::assertSame(1, $resolution->resolved);
        self::assertStringContainsString('range: -5..10', $resolution->overlay);
    }

    /**
     * A flag that merely contains the word fuzzy is no fuzzy flag.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function doesNotTakeAFlagThatOnlyContainsTheWordFuzzyForTheFuzzyFlag(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, not-fuzzy-at-all\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\""),
        );

        self::assertSame(0, $resolution->resolved);
        self::assertSame([], $resolution->unresolved);
    }

    /**
     * Without a previous source text there is nothing to compare, so the entry stays fuzzy.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function reportsAFuzzyEntryWithoutAPreviousSourceText(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\nmsgid \"Births · per year\"\nmsgstr \"Geburten — pro Jahr\""),
        );

        self::assertSame(0, $resolution->resolved);
        self::assertSame(['Births · per year'], $resolution->unresolved);
    }

    /**
     * A fuzzy entry with an empty translation has nothing to carry over and stays fuzzy.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function reportsAFuzzyEntryWithoutATranslation(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"\""),
        );

        self::assertSame(0, $resolution->resolved);
        self::assertSame(['A · b'], $resolution->unresolved);
    }

    /**
     * A plural entry stays fuzzy, because the swap is only defined for a single translation.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function reportsAFuzzyPluralEntry(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#| msgid \"%s child — x\"\nmsgid \"%s child · x\"\nmsgid_plural \"%s children · x\"\nmsgstr[0] \"%s Kind — x\"\nmsgstr[1] \"%s Kinder — x\""),
        );

        self::assertSame(0, $resolution->resolved);
        self::assertSame(['%s child · x'], $resolution->unresolved);
    }

    /**
     * Literals wrapped over several lines are joined and their escapes stay as written.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function joinsWrappedLiteralsAndKeepsEscapesAsTheyAreWritten(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#| msgid \"\"\n#| \"Say \\\"hi\\\" — path C:\\\\x\\n\"\nmsgid \"\"\n\"Say \\\"hi\\\" · path C:\\\\x\\n\"\nmsgstr \"\"\n\"Sag \\\"hi\\\" — Pfad \"\n\"C:\\\\x\\n\""),
        );

        self::assertSame(1, $resolution->resolved);
        self::assertStringContainsString(
            "msgid \"Say \\\"hi\\\" · path C:\\\\x\\n\"\nmsgstr \"Sag \\\"hi\\\" · Pfad C:\\\\x\\n\"",
            $resolution->overlay,
        );
    }

    /**
     * The context of an entry is carried into the overlay when the merge recorded the same one.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function carriesTheContextOfTheEntry(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#| msgctxt \"month\"\n#| msgid \"May — x\"\nmsgctxt \"month\"\nmsgid \"May · x\"\nmsgstr \"Mai — x\""),
        );

        self::assertSame(1, $resolution->resolved);
        self::assertStringContainsString(
            "msgctxt \"month\"\nmsgid \"May · x\"\nmsgstr \"Mai · x\"",
            $resolution->overlay,
        );
    }

    /**
     * An entry whose context changed together with its source text stays fuzzy.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function reportsAnEntryWhoseContextChanged(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#| msgctxt \"month\"\n#| msgid \"May — x\"\nmsgctxt \"verb\"\nmsgid \"May · x\"\nmsgstr \"Mai — x\""),
        );

        self::assertSame(0, $resolution->resolved);
        self::assertSame(['verb|May · x'], $resolution->unresolved);
    }

    /**
     * An entry with a context but without a recorded previous context stays fuzzy, because the context may have been added.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function reportsAnEntryWithAContextThatTheMergeDidNotRecord(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#| msgid \"May — x\"\nmsgctxt \"month\"\nmsgid \"May · x\"\nmsgstr \"Mai — x\""),
        );

        self::assertSame(0, $resolution->resolved);
        self::assertSame(['month|May · x'], $resolution->unresolved);
    }

    /**
     * A fuzzy entry whose previous and current source text are the same was flagged by a person and stays fuzzy.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function reportsAFuzzyEntryWhoseSourceTextDidNotChange(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#| msgid \"A · b\"\nmsgid \"A · b\"\nmsgstr \"X · y\""),
        );

        self::assertSame(0, $resolution->resolved);
        self::assertSame(['A · b'], $resolution->unresolved);
    }

    /**
     * The fuzzy flag is found when no space follows the comma of the flag line.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function readsTheFuzzyFlagWrittenWithoutASpace(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            self::catalogue("#,fuzzy\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\""),
        );

        self::assertSame(1, $resolution->resolved);
    }

    /**
     * A catalogue as msgmerge writes it, with references, flags and wrapped previous texts, resolves to the expected overlay.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function resolvesACatalogueTheWayMsgmergeWritesIt(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            (string) file_get_contents(__DIR__ . '/../fixtures/fuzzy/de/messages.po'),
        );

        self::assertSame(4, $resolution->resolved);
        self::assertSame(['Deaths per century', '%s child · total'], $resolution->unresolved);
        self::assertSame(
            (string) file_get_contents(__DIR__ . '/../fixtures/fuzzy/overlay.po'),
            $resolution->overlay,
        );
    }

    /**
     * The translator comments of an entry are carried into the overlay, so applying it does not drop them.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function carriesTheTranslatorCommentsOfTheEntry(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            self::catalogue(
                "# Keep the dash style\n#\n# Second note\n#: src/A.php:1\n#, fuzzy\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\"",
            ),
        );

        self::assertSame(1, $resolution->resolved);
        self::assertStringContainsString(
            "# Keep the dash style\n#\n# Second note\nmsgid \"A · b\"\nmsgstr \"X · y\"",
            $resolution->overlay,
        );
    }

    /**
     * A header that carries the fuzzy flag is still the header, so it is neither reported nor resolved as an entry.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function treatsAHeaderWithTheFuzzyFlagAsTheHeader(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            "#, fuzzy\n" . self::HEADER . "\n\n#, fuzzy\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\"\n",
        );

        self::assertSame(1, $resolution->resolved);
        self::assertSame([], $resolution->unresolved);
        self::assertStringStartsWith("#, fuzzy\n" . self::HEADER . "\n\n", $resolution->overlay);
    }

    /**
     * The overlay starts with the header of the catalogue, so applying it cannot replace the real header.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function keepsTheHeaderOfTheCatalogueInTheOverlay(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\""),
        );

        self::assertStringStartsWith(self::HEADER . "\n\n", $resolution->overlay);
    }

    /**
     * The flags of an entry are read from all of its flag lines, as gettext reads them.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function readsTheFlagsFromSeveralFlagLines(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#, php-format\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\""),
        );

        self::assertSame(1, $resolution->resolved);
        self::assertSame([], $resolution->unresolved);
    }

    /**
     * An entry with an empty source text and a context is an entry, not the header, so it does not replace the header.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function doesNotTakeAnEntryWithAContextAndNoSourceTextForTheHeader(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue(
                "msgctxt \"c\"\nmsgid \"\"\nmsgstr \"x\"",
                "#, fuzzy\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\"",
            ),
        );

        self::assertSame(1, $resolution->resolved);
        self::assertStringStartsWith(self::HEADER . "\n\n", $resolution->overlay);
    }

    /**
     * A block of comments in front of the header is carried into the overlay, so applying it cannot drop it.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function keepsTheCommentBlocksInFrontOfTheHeader(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            "# License block\n# Header comment\n" . self::HEADER . "\n\n"
            . "#, fuzzy\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\"\n",
        );

        self::assertStringStartsWith("# License block\n# Header comment\nmsgid \"\"\n", $resolution->overlay);
    }

    /**
     * The flags of a resolved entry other than the fuzzy flag are kept, because the template may not carry them.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function keepsTheFlagsOtherThanFuzzyOfAResolvedEntry(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy, no-wrap, c-format\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\""),
        );

        self::assertStringContainsString("#, no-wrap, c-format\nmsgid \"A · b\"", $resolution->overlay);
    }

    /**
     * A block of extracted comments in front of the header is carried into the overlay like one of translator comments.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function keepsAnExtractedCommentBlockInFrontOfTheHeader(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            "# Translator block\n#. extracted note\n" . self::HEADER . "\n\n"
            . "#, fuzzy\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\"\n",
        );

        self::assertStringStartsWith("# Translator block\n#. extracted note\nmsgid \"\"\n", $resolution->overlay);
        self::assertStringNotContainsString('extracted note', $resolution->selection);
    }

    /**
     * The selection holds the resolved entries alone, so neither the header nor the comments in front of it are in it.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function offersTheResolvedEntriesAloneAsTheSelection(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            "# Translator block\n" . self::HEADER . "\n\n"
            . "#, fuzzy\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\"\n",
        );

        self::assertSame("msgid \"A · b\"\nmsgstr \"X · y\"\n", $resolution->selection);
    }

    /**
     * A translator comment is kept whether or not a space follows its hash sign.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function keepsATranslatorCommentWithoutASpaceAfterTheHash(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#note without space\n#, fuzzy\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\""),
        );

        self::assertStringContainsString("#note without space\n", $resolution->overlay);
    }

    /**
     * Provides blocks that the canonical layout of gettext never holds, each of which cannot be read without guessing.
     *
     * @return array<string, array{string}> The rows, keyed by the layout, each with the entries of the catalogue
     */
    public static function blocksTheCanonicalLayoutNeverHolds(): array
    {
        return [
            'entries without a blank line' => [
                "#, fuzzy\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\"\n#, fuzzy\n#| msgid \"C — d\"\nmsgid \"C · d\"\nmsgstr \"Z — w\"",
            ],
            'keyword without a space'               => ["#, fuzzy\n#| msgid \"A — b\"\nmsgid\"A · b\"\nmsgstr \"X — y\""],
            'blank line inside an entry'            => ["#, fuzzy\n#| msgid \"A — b\"\n\nmsgid \"A · b\"\nmsgstr \"X — y\""],
            'comments apart from entry'             => ["# stray note\n\n#, fuzzy\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\""],
            'two literals on one line'              => ["#, fuzzy\n#| msgid \"A - b\"\nmsgid \"A · b\"\nmsgstr \"X -\" \" y\""],
            'entry without a translation'           => ["#, fuzzy\n#| msgid \"A — b\"\nmsgid \"A · b\""],
            'glued entries without a comment line'  => ["#, fuzzy\n#| msgid \"A - b\"\nmsgid \"A · b\"\nmsgstr \"X - y\"\nmsgid \"C\"\nmsgstr \"z\""],
            'second header after the header'        => ["msgid \"\"\nmsgstr \"Language: de\\n\""],
            'literal after the previous text lines' => ["#, fuzzy\n#| msgid \"A - B\"\n\"\"\nmsgid \"A · B\"\nmsgstr \"X - Y\""],
        ];
    }

    /**
     * A block that the canonical layout never holds is refused instead of being read by guessing.
     *
     * @param string $entries The entries of the catalogue after its header
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    #[DataProvider('blocksTheCanonicalLayoutNeverHolds')]
    public function refusesABlockTheCanonicalLayoutNeverHolds(string $entries): void
    {
        $this->expectException(CatalogueLayoutException::class);

        (new PunctuationResolver())->resolve($this->catalogue($entries));
    }

    /**
     * A catalogue without any entry has nothing to resolve and is no layout to refuse.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function resolvesNothingInACatalogueWithoutEntries(): void
    {
        $resolution = (new PunctuationResolver())->resolve('');

        self::assertSame(0, $resolution->resolved);
        self::assertSame([], $resolution->unresolved);
        self::assertSame([], $resolution->live);
    }

    /**
     * A catalogue without a header is refused as a layout, so the run names that catalogue and goes on with the others
     * instead of failing in a tool that needs the charset of the header.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function refusesACatalogueWithoutAHeaderThatHoldsAnEntryToResolve(): void
    {
        $this->expectException(CatalogueLayoutException::class);

        (new PunctuationResolver())->resolve("#, fuzzy\n#| msgid \"A - B\"\nmsgid \"A — B\"\nmsgstr \"X - Y\"\n");
    }

    /**
     * A block that is only the marker of a previous text in front of the header is refused instead of being kept as a comment.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function refusesAMarkerBlockInFrontOfTheHeader(): void
    {
        $this->expectException(CatalogueLayoutException::class);

        (new PunctuationResolver())->resolve(
            "#| \n\n" . $this->catalogue("#, fuzzy\n#| msgid \"A - B\"\nmsgid \"A · B\"\nmsgstr \"X - Y\""),
        );
    }

    /**
     * A header that stands behind an entry is refused, because the comments in front of the entry would move onto it.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function refusesAHeaderBehindAnEntry(): void
    {
        $this->expectException(CatalogueLayoutException::class);
        $this->expectExceptionMessageMatches('/header that is not the first block/');

        (new PunctuationResolver())->resolve(
            "# note\nmsgid \"Z\"\nmsgstr \"z\"\n\n" . $this->catalogue("#, fuzzy\n#| msgid \"A - B\"\nmsgid \"A · B\"\nmsgstr \"X - Y\""),
        );
    }

    /**
     * The identity of a live entry holds every plural translation, so a catalogue that differs in one of them has
     * another identity.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function keepsEveryPluralTranslationInTheIdentityOfAnEntry(): void
    {
        $resolver = new PunctuationResolver();
        $entry    = "msgid \"One\"\nmsgid_plural \"Many\"\nmsgstr[0] \"Eins\"\nmsgstr[1] \"%s\"";

        $first  = $resolver->resolve($this->catalogue(sprintf($entry, 'Viele')));
        $second = $resolver->resolve($this->catalogue(sprintf($entry, 'Mehrere')));

        self::assertCount(1, $first->live);
        self::assertNotSame($first->live, $second->live);
    }

    /**
     * A fuzzy entry with a context and an empty source text is an entry like any other, so it is counted.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function reportsAFuzzyEntryWithAContextAndAnEmptySourceText(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#| msgid \"\"\nmsgctxt \"a\"\nmsgid \"\"\nmsgstr \"y\""),
        );

        self::assertSame(0, $resolution->resolved);
        self::assertSame(['a|'], $resolution->unresolved);
    }

    /**
     * A plural that became a singular keeps its fuzzy flag, because the first plural form is no translation of it.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function reportsAnEntryThatWasAPluralBefore(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue(
                "#, fuzzy\n#| msgid \"Day — total\"\n#| msgid_plural \"Days — total\"\nmsgid \"Day · total\"\nmsgstr \"Tag — gesamt\"",
            ),
        );

        self::assertSame(0, $resolution->resolved);
        self::assertSame(['Day · total'], $resolution->unresolved);
    }

    /**
     * Comments that no header follows are refused, because they would be read in part and dropped.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function refusesACommentBlockThatNoHeaderFollows(): void
    {
        $this->expectException(CatalogueLayoutException::class);

        (new PunctuationResolver())->resolve(
            "#: a.php:1\n#, fuzzy\n\n#| msgid \"Hello world  again\"\nmsgid \"Hello world again\"\nmsgstr \"Hallo Welt\"\n",
        );
    }

    /**
     * A literal with an escaped quote inside is read as one literal, so only a second literal on the line is refused.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function readsALiteralWithAnEscapedQuote(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#| msgid \"Say \\\"hi\\\" - now\"\nmsgid \"Say \\\"hi\\\" · now\"\nmsgstr \"Sag \\\"hi\\\" - jetzt\""),
        );

        self::assertSame(1, $resolution->resolved);
        self::assertStringContainsString('msgstr "Sag \\"hi\\" · jetzt"', $resolution->overlay);
    }

    /**
     * A single long literal line is read like a short one, so one over-long text does not block the catalogue.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function readsAVeryLongLiteralLine(): void
    {
        $long = str_repeat('x', 40000);

        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue(
                "#, fuzzy\n#| msgid \"A - b\"\nmsgid \"A · b\"\nmsgstr \"X - $long\"\n\nmsgid \"Long\"\nmsgstr \"$long\"",
            ),
        );

        self::assertSame(1, $resolution->resolved);
    }

    /**
     * A block that holds nothing but a space is no entry and no comment, so the catalogue is refused.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function refusesABlockOfSpacesOnly(): void
    {
        $this->expectException(CatalogueLayoutException::class);

        (new PunctuationResolver())->resolve($this->catalogue("msgid \"A\"\nmsgstr \"B\"") . "\n \n");
    }

    /**
     * Several entries are resolved in file order while the others are reported.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function resolvesSeveralEntriesAndReportsTheRest(): void
    {
        $resolution = (new PunctuationResolver())->resolve(
            $this->catalogue("#, fuzzy\n#| msgid \"A — b\"\nmsgid \"A · b\"\nmsgstr \"X — y\"", "msgid \"Stable\"\nmsgstr \"Stabil\"", "#, fuzzy\n#| msgid \"Old words\"\nmsgid \"New words\"\nmsgstr \"Alte Worte\"", "#, fuzzy\n#| msgid \"C - d\"\nmsgid \"C – d\"\nmsgstr \"Z - w\""),
        );

        self::assertSame(2, $resolution->resolved);
        self::assertSame(['New words'], $resolution->unresolved);
        self::assertSame(
            $this->catalogue("msgid \"A · b\"\nmsgstr \"X · y\"", "msgid \"C – d\"\nmsgstr \"Z – w\""),
            $resolution->overlay,
        );
    }

    /**
     * A catalogue without fuzzy entries yields no overlay and nothing to report.
     *
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function aCatalogueWithoutFuzzyEntriesResolvesNothing(): void
    {
        $resolution = (new PunctuationResolver())->resolve($this->catalogue("msgid \"A\"\nmsgstr \"B\""));

        self::assertSame(0, $resolution->resolved);
        self::assertSame([], $resolution->unresolved);
        self::assertSame('', $resolution->overlay);
    }
}
