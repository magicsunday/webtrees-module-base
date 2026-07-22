<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Processor;

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Individual;
use Illuminate\Support\Collection;
use MagicSunday\Webtrees\ModuleBase\Processor\NameProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * NameProcessorTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(NameProcessor::class)]
final class NameProcessorTest extends TestCase
{
    /**
     * @return array<string, array{string, list<string>, list<string>}>
     */
    public static function nonAsciiNameDataProvider(): array
    {
        // [ formatted name, expected first names, expected last names ]
        return [
            'utf-8 umlauts' => [
                '<span class="NAME" dir="auto" translate="no">Jörg <span class="SURN">Müller</span></span>',
                ['Jörg'],
                ['Müller'],
            ],
            'named entities in the source markup' => [
                '<span class="NAME" dir="auto" translate="no">J&ouml;rg <span class="SURN">M&uuml;ller</span></span>',
                ['Jörg'],
                ['Müller'],
            ],
            'numeric entities in the source markup' => [
                '<span class="NAME" dir="auto" translate="no">J&#246;rg <span class="SURN">M&#252;ller</span></span>',
                ['Jörg'],
                ['Müller'],
            ],
            'korean' => [
                '<span class="NAME" dir="auto" translate="no">성욱 <span class="SURN">박</span></span>',
                ['성욱'],
                ['박'],
            ],
        ];
    }

    /**
     * Non-ASCII names survive the DOM round-trip. webtrees hands the processor UTF-8
     * (sometimes already entity-encoded); the DOM parser it uses would mangle raw
     * multi-byte input, so the name is entity-encoded on the way in. What must hold
     * for a consumer is that the extracted name reads back as the original
     * characters — never as mojibake or as literal entity text.
     *
     * @param string       $fullNameHtml
     * @param list<string> $expectedFirstNames
     * @param list<string> $expectedLastNames
     *
     * @return void
     */
    #[Test]
    #[DataProvider('nonAsciiNameDataProvider')]
    public function nonAsciiNamesSurviveExtraction(
        string $fullNameHtml,
        array $expectedFirstNames,
        array $expectedLastNames,
    ): void {
        $nameProcessor = $this->nameProcessorFor($fullNameHtml);

        self::assertSame($expectedFirstNames, $nameProcessor->getFirstNames());
        self::assertSame($expectedLastNames, $nameProcessor->getLastNames());
    }

    /**
     * @return array<int, array{string, array{list<string>, list<string>, list<string>}}>
     */
    public static function individualNameDataProvider(): array
    {
        // [ formatted name, expected => [ first names, last names, preferred name ] ]
        return [
            [
                '<span class="NAME" dir="auto" translate="no"><span class="starredname">Max</span> Hermann <span class="SURN">Mustermann</span></span>',
                [
                    [
                        'Max',
                        'Hermann',
                    ],
                    [
                        'Mustermann',
                    ],
                    [
                        'Max',
                    ],
                ],
            ],

            [
                '<span class="NAME" dir="auto" translate="no">Max <span class="starredname">Peter</span> <q class="wt-nickname">Mäxchen</q> <span class="SURN">Mustermann</span></span>',
                [
                    [
                        'Max',
                        'Peter',
                    ],
                    [
                        'Mustermann',
                    ],
                    [
                        'Peter',
                    ],
                ],
            ],

            [
                '<span class="NAME" dir="auto" translate="no">Max <q class="wt-nickname">Mäxchen</q> <span class="starredname">Peter</span> <span class="SURN">Mustermann</span></span>',
                [
                    [
                        'Max',
                        'Peter',
                    ],
                    [
                        'Mustermann',
                    ],
                    [
                        'Peter',
                    ],
                ],
            ],

            [
                '<span class="NAME" dir="auto" translate="no">Max <q class="wt-nickname">Mäxchen</q> Hermann <span class="SURN">Mustermann</span></span>',
                [
                    [
                        'Max',
                        'Hermann',
                    ],
                    [
                        'Mustermann',
                    ],
                    [
                        '',
                    ],
                ],
            ],

            [
                '<span class="NAME" dir="auto" translate="no">José <span class="starredname">Antonio</span> <span class="SURN">Gómez</span> <span class="SURN">Iglesias</span></span>',
                [
                    [
                        'José',
                        'Antonio',
                    ],
                    [
                        'Gómez',
                        'Iglesias',
                    ],
                    [
                        'Antonio',
                    ],
                ],
            ],

            [
                '<span class="NAME" dir="auto" translate="no">José <span class="starredname">Antonio</span> Carlo <span class="SURN">Gómez</span> <span class="SURN">Iglesias</span></span>',
                [
                    [
                        'José',
                        'Antonio',
                        'Carlo',
                    ],
                    [
                        'Gómez',
                        'Iglesias',
                    ],
                    [
                        'Antonio',
                    ],
                ],
            ],
        ];
    }

    /**
     * Builds a NameProcessor the way a consumer does: around an individual, through
     * the real constructor. That constructor is where the work happens — it picks the
     * primary name and builds the XPath instance over it — so constructing for real
     * is what puts those paths under test.
     *
     * @param string $fullNameHtml The individual's formatted primary name (webtrees
     *                             supplies HTML here)
     *
     * @return NameProcessor
     */
    private function nameProcessorFor(string $fullNameHtml): NameProcessor
    {
        $individual = self::createStub(Individual::class);
        $individual->method('getAllNames')->willReturn([
            [
                'full'   => $fullNameHtml,
                'fullNN' => $fullNameHtml,
                'type'   => 'BIRT',
                'surn'   => '',
                'givn'   => '',
                'sort'   => '',
            ],
        ]);
        $individual->method('getPrimaryName')->willReturn(0);

        return new NameProcessor($individual);
    }

    /**
     * Tests extracting the plain first names of an individual.
     *
     * @param string               $input
     * @param array<int, string[]> $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('individualNameDataProvider')]
    public function extractsGivenNameTokensFromTheFormattedName(string $input, array $expected): void
    {
        self::assertSame($expected[0], $this->nameProcessorFor($input)->getFirstNames());
    }

    /**
     * Tests extracting the plain last names of an individual.
     *
     * @param string               $input
     * @param array<int, string[]> $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('individualNameDataProvider')]
    public function extractsSurnameTokensFromTheFormattedName(string $input, array $expected): void
    {
        self::assertSame($expected[1], $this->nameProcessorFor($input)->getLastNames());
    }

    /**
     * Tests extracting the plain first names of an individual.
     *
     * @param string               $input
     * @param array<int, string[]> $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('individualNameDataProvider')]
    public function reportsTheStarredNamePartAsPreferredName(string $input, array $expected): void
    {
        // getPreferredName returns only one match, but test data is stored as an array
        self::assertSame($expected[2][0], $this->nameProcessorFor($input)->getPreferredName());
    }

    /**
     * Reports the married surnames an individual with the given NAME records has,
     * built the way a consumer builds it: around a stubbed individual, through the
     * real constructor, calling the public method.
     *
     * @param array<int, array<string, string>> $individualNames The individual's NAME records
     * @param Individual|null                   $spouse          The spouse whose surname must match, if any
     *
     * @return string[]
     */
    private function marriedSurnamesFor(array $individualNames, ?Individual $spouse = null): array
    {
        $individualStub = self::createStub(Individual::class);

        // The cases below describe only what getMarriedSurnames() reads (type +
        // surn). The constructor additionally needs a primary name it can build its
        // XPath over, so fill in the remaining keys — `+` keeps whatever the case
        // already specified.
        $individualStub->method('getAllNames')->willReturn(array_map(
            static fn (array $name): array => $name + [
                'full'   => '<span class="NAME" dir="auto" translate="no">Test <span class="SURN">Person</span></span>',
                'fullNN' => 'Test Person',
                'givn'   => '',
                'surn'   => '',
            ],
            $individualNames
        ));
        $individualStub->method('getPrimaryName')->willReturn(0);

        return (new NameProcessor($individualStub))->getMarriedSurnames($spouse);
    }

    /**
     * An individual with only a plain NAME record has no married surname to report.
     *
     * @return void
     */
    #[Test]
    public function getMarriedSurnamesReturnsEmptyWhenNoMarnmRecord(): void
    {
        self::assertSame([], $this->marriedSurnamesFor([
            ['type' => 'NAME', 'surn' => 'Schmidt'],
        ]));
    }

    /**
     * With no spouse to match against, the _MARNM surname is reported as-is.
     *
     * @return void
     */
    #[Test]
    public function getMarriedSurnamesReturnsMarnmSurnameWhenNoSpouseGiven(): void
    {
        self::assertSame(['Müller'], $this->marriedSurnamesFor([
            ['type' => 'NAME', 'surn' => 'Schmidt'],
            ['type' => '_MARNM', 'surn' => 'Müller'],
        ]));
    }

    /**
     * A _MARNM surname holding several space-separated parts is split into one entry per part.
     *
     * @return void
     */
    #[Test]
    public function getMarriedSurnamesSplitsMultipleSurnameParts(): void
    {
        self::assertSame(['Müller', 'Meier'], $this->marriedSurnamesFor([
            ['type' => '_MARNM', 'surn' => 'Müller Meier'],
        ]));
    }

    /**
     * Given a spouse, only the _MARNM record whose surname matches that spouse is reported —
     * an individual can carry several married names from different marriages.
     *
     * @return void
     */
    #[Test]
    public function getMarriedSurnamesMatchesSpouseSurname(): void
    {
        $spouseStub = self::createStub(Individual::class);
        $spouseStub->method('getAllNames')->willReturn([
            ['type' => 'NAME', 'surn' => 'Müller'],
        ]);

        // The first _MARNM ("Andere") doesn't match the spouse's surname,
        // so it must be skipped; the second _MARNM ("Müller") matches.
        self::assertSame(['Müller'], $this->marriedSurnamesFor(
            [
                ['type' => 'NAME', 'surn' => 'Schmidt'],
                ['type' => '_MARNM', 'surn' => 'Andere'],
                ['type' => '_MARNM', 'surn' => 'Müller'],
            ],
            $spouseStub
        ));
    }

    /**
     * When no _MARNM record matches the spouse's surname, nothing is reported rather than
     * falling back to an unrelated married name.
     *
     * @return void
     */
    #[Test]
    public function getMarriedSurnamesReturnsEmptyWhenSpouseSurnameDoesNotMatchAnyMarnm(): void
    {
        $spouseStub = self::createStub(Individual::class);
        $spouseStub->method('getAllNames')->willReturn([
            ['type' => 'NAME', 'surn' => 'Schmidt'],
        ]);

        self::assertSame([], $this->marriedSurnamesFor(
            [
                ['type' => '_MARNM', 'surn' => 'Müller'],
            ],
            $spouseStub
        ));
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function injectNicknameDataProvider(): array
    {
        // [ formatted name, plain name, nick, expected ]
        return [
            'Empty nick returns input unchanged' => [
                '<span class="NAME" dir="auto" translate="no">John <span class="SURN">Doe</span></span>',
                'John Doe', '', 'John Doe',
            ],
            'Inserts after last given name (single-word surname)' => [
                '<span class="NAME" dir="auto" translate="no">John <span class="SURN">Doe</span></span>',
                'John Doe', 'Jonny', 'John "Jonny" Doe',
            ],
            'Multiple given names: inserts after the last one' => [
                '<span class="NAME" dir="auto" translate="no">Friedrich Wilhelm August <span class="SURN">von</span> <span class="SURN">Habsburg-Lothringen</span></span>',
                'Friedrich Wilhelm August von Habsburg-Lothringen',
                'Fritz',
                'Friedrich Wilhelm August "Fritz" von Habsburg-Lothringen',
            ],
            'Surname particle in given-name area: nickname stays before particle+surname' => [
                '<span class="NAME" dir="auto" translate="no">Friedrich von <span class="SURN">Berg</span></span>',
                'Friedrich von Berg',
                'Fritz',
                'Friedrich von "Fritz" Berg',
            ],
            'Last given name absent from the plain name: appends instead of inserting' => [
                // The formatted name yields given "Ludwig" / surname "Beethoven", but the
                // plain name carries neither given name — so the search inside the
                // pre-surname slice finds nothing and the nickname is appended.
                '<span class="NAME" dir="auto" translate="no">Ludwig <span class="SURN">Beethoven</span></span>',
                'van Beethoven', 'Luigi', 'van Beethoven "Luigi"',
            ],
            'Surname absent from the plain name: the whole string stays searchable' => [
                // strpos() cannot locate "Mustermann" in the plain name, so the haystack
                // is not truncated and the given name is still found in the full string.
                '<span class="NAME" dir="auto" translate="no">Max <span class="SURN">Mustermann</span></span>',
                'Max Schmidt', 'Maxi', 'Max "Maxi" Schmidt',
            ],
            'Idempotent: nick already inline' => [
                '<span class="NAME" dir="auto" translate="no">John <span class="SURN">Doe</span></span>',
                'John "Jonny" Doe', 'Jonny', 'John "Jonny" Doe',
            ],
            'No given names: appends nick' => [
                '<span class="NAME" dir="auto" translate="no"><span class="SURN">Anonymous</span></span>',
                'Anonymous', 'Jonny', 'Anonymous "Jonny"',
            ],
            'Hits last occurrence when given name repeats' => [
                '<span class="NAME" dir="auto" translate="no">Maria Anna Maria <span class="SURN">Schmidt</span></span>',
                'Maria Anna Maria Schmidt',
                'Mimi',
                'Maria Anna Maria "Mimi" Schmidt',
            ],
            'Last given name is substring of surname' => [
                // Issue 199: strrpos used to anchor on "Jan" inside "Jansen",
                // splitting the surname and producing "Hendrik Jan Jan \"Henk\"sen".
                '<span class="NAME" dir="auto" translate="no">Hendrik Jan <span class="SURN">Jansen</span></span>',
                'Hendrik Jan Jansen',
                'Henk',
                'Hendrik Jan "Henk" Jansen',
            ],
            'Last given name is substring of surname (second sample)' => [
                '<span class="NAME" dir="auto" translate="no">Pieter Jan <span class="SURN">Jansen</span></span>',
                'Pieter Jan Jansen',
                'Piet',
                'Pieter Jan "Piet" Jansen',
            ],
            'Empty last names list falls back to whole-string search' => [
                // No surname tokens (a mononym): the search range stays the whole
                // string, anchoring on the last given name.
                '<span class="NAME" dir="auto" translate="no">Madonna</span>',
                'Madonna',
                'Madge',
                'Madonna "Madge"',
            ],
        ];
    }

    /**
     * Builds a NameProcessor whose individual also reports a GEDCOM nickname, so the
     * nickname-injecting path can be driven from the public API.
     *
     * @param string $fullNameHtml The formatted (HTML) primary name
     * @param string $plainName    The same name without markup, as `fullNN` carries it
     * @param string $nick         The GEDCOM `2 NICK` value, or an empty string
     *
     * @return NameProcessor
     */
    private function nameProcessorWithNickname(
        string $fullNameHtml,
        string $plainName,
        string $nick,
    ): NameProcessor {
        $nameFact = self::createStub(Fact::class);
        $nameFact->method('attribute')->willReturnMap([
            ['TYPE', ''],
            ['NICK', $nick],
        ]);

        $individual = self::createStub(Individual::class);
        $individual->method('facts')->willReturn(new Collection([$nameFact]));
        $individual->method('getAllNames')->willReturn([
            [
                'full'   => $fullNameHtml,
                'fullNN' => $plainName,
                'type'   => 'BIRT',
                'surn'   => '',
                'givn'   => '',
                'sort'   => '',
            ],
        ]);
        $individual->method('getPrimaryName')->willReturn(0);

        return new NameProcessor($individual);
    }

    /**
     * Tests that the nickname is quoted and placed after the last given name, so it
     * lands before whatever follows (a surname particle plus surname, or the surname
     * itself) and never splits the surname when a given name is a substring of it.
     *
     * @param string $fullNameHtml
     * @param string $plainName
     * @param string $nick
     * @param string $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('injectNicknameDataProvider')]
    public function injectsQuotedNicknameAfterTheLastGivenName(
        string $fullNameHtml,
        string $plainName,
        string $nick,
        string $expected,
    ): void {
        self::assertSame(
            $expected,
            $this->nameProcessorWithNickname($fullNameHtml, $plainName, $nick)->getFullNameWithNickname()
        );
    }

    /**
     * getFullName() reports the unformatted name and rewrites webtrees' "name
     * unknown" placeholders into an ellipsis, so a consumer never renders the raw
     * GEDCOM sentinel to a user.
     *
     * @return void
     */
    #[Test]
    public function getFullNameReplacesUnknownNamePlaceholders(): void
    {
        $processor = $this->nameProcessorWithNickname(
            '<span class="NAME" dir="auto" translate="no">John <span class="SURN">Doe</span></span>',
            Individual::PRAENOMEN_NESCIO . ' ' . Individual::NOMEN_NESCIO,
            ''
        );

        self::assertSame('… …', $processor->getFullName());
    }

    /**
     * The nickname is read from the primary NAME fact only. A nickname carried by a
     * married-name or also-known-as variant belongs to that identity, not to the
     * birth identity getFullName() reports, so it must be skipped.
     *
     * @return void
     */
    #[Test]
    public function getNicknameSkipsNonPrimaryNameVariants(): void
    {
        $marriedName = self::createStub(Fact::class);
        $marriedName->method('attribute')->willReturnMap([
            ['TYPE', '_MARNM'],
            ['NICK', 'Married-Nick'],
        ]);

        // An explicit lower-case "birth" TYPE: the guard upper-cases before comparing,
        // so this must still be accepted as the primary identity.
        $birthName = self::createStub(Fact::class);
        $birthName->method('attribute')->willReturnMap([
            ['TYPE', 'birth'],
            ['NICK', 'Jonny'],
        ]);

        $individual = self::createStub(Individual::class);
        $individual->method('facts')->willReturn(new Collection([$marriedName, $birthName]));
        $individual->method('getAllNames')->willReturn([
            [
                'full'   => '<span class="NAME" dir="auto" translate="no">John <span class="SURN">Doe</span></span>',
                'fullNN' => 'John Doe',
                'type'   => 'BIRT',
                'surn'   => '',
                'givn'   => '',
                'sort'   => '',
            ],
        ]);
        $individual->method('getPrimaryName')->willReturn(0);

        self::assertSame('Jonny', (new NameProcessor($individual))->getNickname());
    }
}
