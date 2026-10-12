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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Pins what the reader takes from a catalogue in the canonical layout.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(CatalogueEntryReader::class)]
#[UsesClass(CatalogueBlockParser::class)]
#[UsesClass(ParsedEntry::class)]
#[UsesClass(PrintableText::class)]
final class CatalogueEntryReaderTest extends TestCase
{
    /**
     * The header is kept as written, and obsolete entries and the header are no entries.
     */
    #[Test]
    public function keepsTheHeaderAndLeavesObsoleteEntriesOut(): void
    {
        $header = "msgid \"\"\nmsgstr \"\"\n\"Language: de\\n\"";

        $read = (new CatalogueEntryReader())->read(
            $header . "\n\n#~ msgid \"Old\"\n#~ msgstr \"Alt\"\n\nmsgid \"New\"\nmsgstr \"Neu\"\n",
        );

        self::assertSame($header, $read['header']);
        self::assertSame(['New'], array_keys($read['entries']));
        self::assertSame(['Neu'], $read['entries']['New']['forms']);
    }

    /**
     * A context, a plural text, the forms and the fuzzy flag are read, and a literal split over lines is joined.
     */
    #[Test]
    public function readsContextPluralFormsFuzzyAndSplitLiterals(): void
    {
        $read = (new CatalogueEntryReader())->read(
            "msgid \"\"\nmsgstr \"\"\n\n"
            . "#, fuzzy\nmsgctxt \"menu\"\nmsgid \"File\"\nmsgstr \"Datei\"\n\n"
            . "msgid \"One\"\nmsgid_plural \"Many\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n\n"
            . "msgid \"\"\n\"Split \"\n\"text\"\nmsgstr \"a\\n\"\n\"b\"\n",
        );

        $menu = $read['entries']["menu\x04File"];

        self::assertSame('menu', $menu['context']);
        self::assertTrue($menu['fuzzy']);

        $plural = $read['entries']['One'];

        self::assertSame('Many', $plural['plural']);
        self::assertSame(['', ''], $plural['forms']);
        self::assertFalse($plural['fuzzy']);

        self::assertSame(["a\nb"], $read['entries']['Split text']['forms']);
    }

    /**
     * Quote, tab, carriage return and the control characters gettext writes as letters are written escaped.
     */
    #[Test]
    public function writesEveryEscapedCharacterAsItsEscape(): void
    {
        self::assertSame(
            '"\\" \\\\ \\t \\r \\n \\a \\b \\f \\v"',
            CatalogueEntryReader::literal("\" \\ \t \r \n \x07 \x08 \x0C \x0B"),
        );
    }

    /**
     * A flag line with several flags marks the entry fuzzy, and the previous text does not disturb the read.
     */
    #[Test]
    public function readsFuzzyFromAFlagLineWithSeveralFlags(): void
    {
        $read = (new CatalogueEntryReader())->read(
            "msgid \"\"\nmsgstr \"\"\n\n"
            . "#, fuzzy, php-format\n#| msgid \"Old\"\nmsgid \"New\"\nmsgstr \"Neu\"\n",
        );

        self::assertTrue($read['entries']['New']['fuzzy']);
        self::assertSame(['Neu'], $read['entries']['New']['forms']);
    }

    /**
     * A text is written as a literal that reads back as the same text.
     */
    #[Test]
    public function writesALiteralThatReadsBackAsTheSameText(): void
    {
        $text = "A \"quoted\" back\\slash,\ttab, cr\r, bell\x07, backspace\x08, form\x0C, vertical\x0B and\nline";

        $read = (new CatalogueEntryReader())->read(
            "msgid \"\"\nmsgstr \"\"\n\nmsgid \"K\"\nmsgstr " . CatalogueEntryReader::literal($text) . "\n",
        );

        self::assertSame([$text], $read['entries']['K']['forms']);
    }
}
