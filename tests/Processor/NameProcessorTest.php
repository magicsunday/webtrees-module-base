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
use MagicSunday\Webtrees\ModuleBase\Model\NameSegment;
use MagicSunday\Webtrees\ModuleBase\Model\NameSegmentKind;
use MagicSunday\Webtrees\ModuleBase\Processor\NameProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
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
#[UsesClass(NameSegment::class)]
#[UsesClass(NameSegmentKind::class)]
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
     * Builds an individual stub whose sole primary NAME record carries the given
     * formatted and plain name — the shape the constructor needs to pick the primary
     * name and build its XPath instance over it. When a facts collection is supplied
     * it is returned from facts(), so the nickname-reading paths can be driven too.
     *
     * @param string                     $fullNameHtml The formatted (HTML) primary name webtrees supplies
     * @param string                     $plainName    The same name without markup, as `fullNN` carries it
     * @param Collection<int, Fact>|null $facts        The individual's facts, when a NICK path is exercised
     *
     * @return Individual
     */
    private function individualWithPrimaryName(
        string $fullNameHtml,
        string $plainName,
        ?Collection $facts = null,
    ): Individual {
        $individual = self::createStub(Individual::class);

        if ($facts instanceof Collection) {
            $individual->method('facts')->willReturn($facts);
        }

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

        return $individual;
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
        return new NameProcessor($this->individualWithPrimaryName($fullNameHtml, $fullNameHtml));
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

        return new NameProcessor(
            $this->individualWithPrimaryName($fullNameHtml, $plainName, new Collection([$nameFact]))
        );
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

        $individual = $this->individualWithPrimaryName(
            '<span class="NAME" dir="auto" translate="no">John <span class="SURN">Doe</span></span>',
            'John Doe',
            new Collection([$marriedName, $birthName])
        );

        self::assertSame('Jonny', (new NameProcessor($individual))->getNickname());
    }

    /**
     * @return array<string, array{string, string, list<array{string, string, bool}>}>
     */
    public static function nameSegmentDataProvider(): array
    {
        // [ formatted name, GEDCOM nickname, expected => [ label, kind, preferred ] ]
        return [
            'given name and surname' => [
                '<span class="NAME" dir="auto" translate="no">Jörg <span class="SURN">Müller</span></span>',
                '',
                [['Jörg', 'given', false], ['Müller', 'surname', false]],
            ],
            'starred name marks only its own token' => [
                '<span class="NAME" dir="auto" translate="no">José <span class="starredname">Antonio</span> Carlo <span class="SURN">Gómez</span> <span class="SURN">Iglesias</span></span>',
                '',
                [
                    ['José', 'given', false],
                    ['Antonio', 'given', true],
                    ['Carlo', 'given', false],
                    ['Gómez', 'surname', false],
                    ['Iglesias', 'surname', false],
                ],
            ],
            'surname particle stays in the given-name area' => [
                '<span class="NAME" dir="auto" translate="no">Friedrich von <span class="SURN">Berg</span></span>',
                '',
                [['Friedrich', 'given', false], ['von', 'given', false], ['Berg', 'surname', false]],
            ],
            'a repeated token keeps its own role and preferred mark' => [
                '<span class="NAME" dir="auto" translate="no">Max <span class="starredname">Max</span> <span class="SURN">Max</span></span>',
                '',
                [['Max', 'given', false], ['Max', 'given', true], ['Max', 'surname', false]],
            ],
            'surname only' => [
                '<span class="NAME" dir="auto" translate="no"><span class="SURN">Anonymous</span></span>',
                '',
                [['Anonymous', 'surname', false]],
            ],
            'mononym has no surname segment' => [
                '<span class="NAME" dir="auto" translate="no">Madonna</span>',
                '',
                [['Madonna', 'given', false]],
            ],
            'a suffix after the surname is a surname segment' => [
                '<span class="NAME" dir="auto" translate="no">Anna <span class="SURN">Schmidt</span> Jr.</span>',
                '',
                [['Anna', 'given', false], ['Schmidt', 'surname', false], ['Jr.', 'surname', false]],
            ],
            'every token of a starred name is preferred' => [
                '<span class="NAME" dir="auto" translate="no"><span class="starredname">Anna Maria</span> Eva <span class="SURN">Schmidt</span></span>',
                '',
                [
                    ['Anna', 'given', true],
                    ['Maria', 'given', true],
                    ['Eva', 'given', false],
                    ['Schmidt', 'surname', false],
                ],
            ],
            'a nickname without any name part stands alone' => [
                '<span class="NAME" dir="auto" translate="no"></span>',
                'Jonny',
                [['"Jonny"', 'nickname', false]],
            ],
            'korean name' => [
                '<span class="NAME" dir="auto" translate="no">성욱 <span class="SURN">박</span></span>',
                '',
                [['성욱', 'given', false], ['박', 'surname', false]],
            ],
            'nickname follows the last given name' => [
                '<span class="NAME" dir="auto" translate="no">Friedrich Wilhelm von <span class="SURN">Berg</span></span>',
                'Fritz',
                [
                    ['Friedrich', 'given', false],
                    ['Wilhelm', 'given', false],
                    ['von', 'given', false],
                    ['"Fritz"', 'nickname', false],
                    ['Berg', 'surname', false],
                ],
            ],
            'nickname follows the surnames when there is no given name' => [
                '<span class="NAME" dir="auto" translate="no"><span class="SURN">Anonymous</span></span>',
                'Jonny',
                [['Anonymous', 'surname', false], ['"Jonny"', 'nickname', false]],
            ],
            'an inline nickname element is not read as a given name' => [
                '<span class="NAME" dir="auto" translate="no">Max <q class="wt-nickname">Mäxchen</q> <span class="starredname">Peter</span> <span class="SURN">Mustermann</span></span>',
                '',
                [['Max', 'given', false], ['Peter', 'given', true], ['Mustermann', 'surname', false]],
            ],
        ];
    }

    /**
     * Tests that the segments follow the display order and carry their role.
     *
     * @param string                            $fullNameHtml
     * @param string                            $nick
     * @param list<array{string, string, bool}> $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('nameSegmentDataProvider')]
    public function reportsTheNameAsOrderedSegments(string $fullNameHtml, string $nick, array $expected): void
    {
        $segments = $this->nameProcessorWithNickname($fullNameHtml, $fullNameHtml, $nick)
            ->getNameSegments($nick !== '');

        self::assertSame(
            $expected,
            array_map(
                static fn (NameSegment $segment): array => [$segment->label, $segment->kind->value, $segment->isPreferred],
                $segments
            )
        );
    }

    /**
     * Without the nickname option the GEDCOM nickname stays out of the list.
     *
     * @return void
     */
    #[Test]
    public function leavesTheNicknameOutUnlessAskedFor(): void
    {
        $processor = $this->nameProcessorWithNickname(
            '<span class="NAME" dir="auto" translate="no">John <span class="SURN">Doe</span></span>',
            'John Doe',
            'Jonny'
        );

        self::assertSame(
            ['John', 'Doe'],
            array_map(static fn (NameSegment $segment): string => $segment->label, $processor->getNameSegments())
        );
    }

    /**
     * The married surname closes the list as one bracketed segment, after the
     * nickname position, and only when a married name record exists.
     *
     * @return void
     */
    #[Test]
    public function appendsTheMarriedSurnameInBrackets(): void
    {
        $individual = self::createStub(Individual::class);
        $individual->method('getAllNames')->willReturn([
            [
                'full'   => '<span class="NAME" dir="auto" translate="no">Anna <span class="SURN">Schmidt</span></span>',
                'fullNN' => 'Anna Schmidt',
                'type'   => 'BIRT',
                'surn'   => 'Schmidt',
                'givn'   => 'Anna',
                'sort'   => '',
            ],
            [
                'full'   => '',
                'fullNN' => '',
                'type'   => '_MARNM',
                'surn'   => 'Müller Meier',
                'givn'   => '',
                'sort'   => '',
            ],
        ]);
        $individual->method('getPrimaryName')->willReturn(0);

        $processor = new NameProcessor($individual);
        $married   = $processor->getNameSegments(false, true);

        self::assertSame(
            ['Anna', 'Schmidt', '(Müller Meier)'],
            array_map(static fn (NameSegment $segment): string => $segment->label, $married)
        );
        self::assertSame(NameSegmentKind::Married, $married[2]->kind);
        self::assertSame(
            ['Anna', 'Schmidt'],
            array_map(static fn (NameSegment $segment): string => $segment->label, $processor->getNameSegments())
        );
    }

    /**
     * A name without a married name record gets no married segment, even when asked for.
     *
     * @return void
     */
    #[Test]
    public function omitsTheMarriedSegmentWithoutAMarriedNameRecord(): void
    {
        $segments = $this->nameProcessorFor(
            '<span class="NAME" dir="auto" translate="no">Anna <span class="SURN">Schmidt</span></span>'
        )->getNameSegments(false, true);

        self::assertSame(
            ['Anna', 'Schmidt'],
            array_map(static fn (NameSegment $segment): string => $segment->label, $segments),
            'given name and surname only, no married segment'
        );
    }

    /**
     * Builds a processor for a woman with a birth name and two married names, so the spouse
     * decides which married surname the segment list closes with.
     *
     * @return NameProcessor
     */
    private function nameProcessorWithTwoMarriedNames(): NameProcessor
    {
        $individual = self::createStub(Individual::class);
        $nameFact   = self::createStub(Fact::class);
        $nameFact->method('attribute')->willReturnMap([
            ['TYPE', ''],
            ['NICK', 'Annie'],
        ]);
        $individual->method('facts')->willReturn(new Collection([$nameFact]));
        $individual->method('getAllNames')->willReturn([
            [
                'full'   => '<span class="NAME" dir="auto" translate="no">Anna <span class="SURN">Schmidt</span></span>',
                'fullNN' => 'Anna Schmidt',
                'type'   => 'BIRT',
                'surn'   => 'Schmidt',
                'givn'   => 'Anna',
                'sort'   => '',
            ],
            [
                'full'   => '',
                'fullNN' => '',
                'type'   => '_MARNM',
                'surn'   => 'Erster',
                'givn'   => '',
                'sort'   => '',
            ],
            [
                'full'   => '',
                'fullNN' => '',
                'type'   => '_MARNM',
                'surn'   => 'Zweiter',
                'givn'   => '',
                'sort'   => '',
            ],
        ]);
        $individual->method('getPrimaryName')->willReturn(0);

        return new NameProcessor($individual);
    }

    /**
     * The spouse selects the married name the segment list closes with, the same way
     * getMarriedSurnames() selects it.
     *
     * @return void
     */
    #[Test]
    public function closesTheListWithTheMarriedSurnameOfTheGivenSpouse(): void
    {
        $spouse = self::createStub(Individual::class);
        $spouse->method('getAllNames')->willReturn([
            ['type' => 'NAME', 'surn' => 'Zweiter'],
        ]);

        $segments = $this->nameProcessorWithTwoMarriedNames()->getNameSegments(false, true, $spouse);

        self::assertSame('(Zweiter)', $segments[2]->label);
    }

    /**
     * A spouse whose surname matches no married name record leaves the list without a
     * married segment.
     *
     * @return void
     */
    #[Test]
    public function leavesOutTheMarriedSurnameWhenNoRecordMatchesTheSpouse(): void
    {
        $spouse = self::createStub(Individual::class);
        $spouse->method('getAllNames')->willReturn([
            ['type' => 'NAME', 'surn' => 'Dritter'],
        ]);

        $segments = $this->nameProcessorWithTwoMarriedNames()->getNameSegments(false, true, $spouse);

        self::assertSame(
            ['Anna', 'Schmidt'],
            array_map(static fn (NameSegment $segment): string => $segment->label, $segments)
        );
    }

    /**
     * With both options the list reads given name, nickname, surname, married surname.
     *
     * @return void
     */
    #[Test]
    public function keepsTheNicknameBeforeTheSurnameWhenTheMarriedSurnameIsAdded(): void
    {
        $segments = $this->nameProcessorWithTwoMarriedNames()->getNameSegments(true, true);

        self::assertSame(
            [
                ['Anna', NameSegmentKind::Given],
                ['"Annie"', NameSegmentKind::Nickname],
                ['Schmidt', NameSegmentKind::Surname],
                ['(Erster)', NameSegmentKind::Married],
            ],
            array_map(
                static fn (NameSegment $segment): array => [$segment->label, $segment->kind],
                $segments
            )
        );
    }
}
