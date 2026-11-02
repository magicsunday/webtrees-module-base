<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Processor;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Date\AbstractCalendarDate;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Factories\CalendarDateFactory;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Illuminate\Support\Collection;
use MagicSunday\Webtrees\ModuleBase\Model\LifetimeLabels;
use MagicSunday\Webtrees\ModuleBase\Model\Symbols;
use MagicSunday\Webtrees\ModuleBase\Processor\DateProcessor;
use MagicSunday\Webtrees\ModuleBase\Support\PlainText;
use MagicSunday\Webtrees\ModuleBase\Test\Double\InMemoryCacheFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function strip_tags;

/**
 * DateProcessorTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(DateProcessor::class)]
#[UsesClass(LifetimeLabels::class)]
#[UsesClass(Symbols::class)]
#[UsesClass(PlainText::class)]
final class DateProcessorTest extends TestCase
{
    /**
     * Set up.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Registry::cache(new InMemoryCacheFactory());
        Registry::calendarDateFactory(new CalendarDateFactory());
    }

    /**
     * @return array<int, array{string, int}>
     */
    public static function yearDataProvider(): array
    {
        // [ input, expected ]
        return [
            [
                '01 MAY 2000',
                2000,
            ],
            [
                'BET SEP 2000 AND AUG 2001',
                2000,
            ],
        ];
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function factDateDataProvider(): array
    {
        // [ GEDCOM date, compact format, expected text ]
        return [
            'an empty date gives nothing'               => ['', '%d.%m.%Y', ''],
            'an unparseable date gives nothing'         => ['not a date', '%d.%m.%Y', ''],
            'a complete date uses the compact format'   => ['12 MAY 1832', '%d.%m.%Y', '12.05.1832'],
            'a complete date follows the given pattern' => ['12 MAY 1832', '%Y/%m/%d', '1832/05/12'],
            // The era text in the year is how webtrees formats a julian year, the rule under test is only
            // that a complete date takes the compact branch.
            'a complete julian date uses the julian year' => ['@#DJULIAN@ 01 MAR 1700', '%Y-%m-%d', '1700 CE-03-01'],
            'a date without a day keeps its wording'      => ['MAY 1832', '%d.%m.%Y', 'May 1832'],
            'a year only keeps its wording'               => ['1832', '%d.%m.%Y', '1832'],
            'a qualified date keeps its qualifier'        => ['ABT 12 MAY 1832', '%d.%m.%Y', 'about May 12, 1832'],
            'a range keeps both ends'                     => ['BET 1800 AND 1810', '%d.%m.%Y', 'between 1800 and 1810'],
        ];
    }

    /**
     * A fact date is compact for a complete date and keeps the webtrees wording for a
     * partial, qualified or ranged one, so "ABT 1832" never collapses to a bare year.
     *
     * @param string $input         The GEDCOM date
     * @param string $compactFormat The compact format pattern
     * @param string $expected      The expected text
     *
     * @return void
     */
    #[Test]
    #[DataProvider('factDateDataProvider')]
    public function formatsAFactDateInTheCompactFormatOrKeepsItsWording(
        string $input,
        string $compactFormat,
        string $expected,
    ): void {
        I18N::init('en-US', true);

        self::assertSame($expected, DateProcessor::formatFactDate(new Date($input), $compactFormat));
    }

    /**
     * The answer for a date that is not valid is empty by contract, whatever text the date would display.
     *
     * @return void
     */
    #[Test]
    public function givesNothingForAnInvalidFactDateWhateverItDisplays(): void
    {
        $date = self::createStub(Date::class);
        $date->method('isOK')->willReturn(false);
        $date->method('display')->willReturn('<span class="date">junk</span>');

        self::assertSame('', DateProcessor::formatFactDate($date, '%d.%m.%Y'));
    }

    /**
     * The text of a date that keeps its wording is trimmed and every run of spaces, including a
     * non-breaking one, becomes a single space.
     *
     * @return void
     */
    #[Test]
    public function trimsAndCollapsesTheWhitespaceOfAFactDateText(): void
    {
        $date = self::createStub(Date::class);
        $date->method('isOK')->willReturn(true);
        $date->method('minimumDate')->willReturn(self::createStub(AbstractCalendarDate::class));
        $date->method('display')->willReturn("<span class=\"date\"> about\u{00A0}\u{00A0} 1832 </span>");

        self::assertSame('about 1832', DateProcessor::formatFactDate($date, '%d.%m.%Y'));
    }

    /**
     * Without a pattern the locale neutral ISO format is used, like for the other compact dates.
     *
     * @return void
     */
    #[Test]
    public function formatsACompleteFactDateInTheFallbackFormatByDefault(): void
    {
        I18N::init('en-US', true);

        self::assertSame('1832-05-12', DateProcessor::formatFactDate(new Date('12 MAY 1832')));
    }

    /**
     * @return array<string, array{string, string, string, string, string}>
     */
    public static function lifetimeFormatEdgeCaseDataProvider(): array
    {
        // [ GEDCOM birth date, GEDCOM death date, born text, died text, expected label ]
        return [
            'a percent sign in the born text'        => ['01 MAY 1853', '', '100% born %s', 'died %s', '100% born 1853'],
            'a percent sign in the died text'        => ['', '12 JUN 1933', 'born %s', '50%d died %s', '50%d died 1933'],
            'an unknown specifier in the text'       => ['01 MAY 1853', '', 'born %y %s', 'died %s', 'born %y 1853'],
            'a second placeholder in the text'       => ['01 MAY 1853', '', '%s (b. %s)', 'died %s', '1853 (b. 1853)'],
            'a missing placeholder in the born text' => ['01 MAY 1853', '', 'born', 'died %s', 'born'],
            'a missing placeholder in the died text' => ['', '12 JUN 1933', 'born %s', 'died', 'died'],
            'an unknown specifier in the died text'  => ['', '12 JUN 1933', 'born %s', 'died %y %s', 'died %y 1933'],
            'a second placeholder in the died text'  => ['', '12 JUN 1933', 'born %s', '%s (d. %s)', '1933 (d. 1933)'],
        ];
    }

    /**
     * The caller has already run its text through one format pass when it reaches the library, so
     * the library only places the year. A percent sign, an unknown specifier, a further placeholder
     * or no placeholder at all must therefore neither raise an error nor garble the year.
     *
     * @param string $birthDate The GEDCOM birth date
     * @param string $deathDate The GEDCOM death date
     * @param string $bornText  The text for a birth year only
     * @param string $diedText  The text for a death year only
     * @param string $expected  The expected label
     *
     * @return void
     */
    #[Test]
    #[DataProvider('lifetimeFormatEdgeCaseDataProvider')]
    public function placesTheYearIntoAnyTextOfTheCaller(
        string $birthDate,
        string $deathDate,
        string $bornText,
        string $diedText,
        string $expected,
    ): void {
        $labels = new LifetimeLabels($bornText, $diedText, 'deceased');

        self::assertSame(
            $expected,
            $this->dateProcessorFor($birthDate, $deathDate, false)->getLifetimeDescription($labels),
        );
    }

    /**
     * @return array<string, array{string, string, bool, string}>
     */
    public static function lifetimeDescriptionDataProvider(): array
    {
        // [ GEDCOM birth date, GEDCOM death date, individual is dead, expected text ]
        return [
            'both years give the range'                   => ['01 MAY 1853', '12 JUN 1933', true, '1853-1933'],
            'a birth year alone uses the born text'       => ['01 MAY 1853', '', false, 'born 1853'],
            'a death year alone uses the died text'       => ['', '12 JUN 1933', true, 'died 1933'],
            'a dead person without dates is deceased'     => ['', '', true, 'deceased'],
            'a living person without dates gives nothing' => ['', '', false, ''],
        ];
    }

    /**
     * The lifetime label takes its texts from the caller, so the library never translates and a
     * consumer's catalogue holds the texts. The texts are made up here, so a pass proves that the
     * texts the caller supplies reach the result and not a text the library carries.
     *
     * @param string $birthDate The GEDCOM birth date
     * @param string $deathDate The GEDCOM death date
     * @param bool   $isDead    Whether webtrees considers the individual deceased
     * @param string $expected  The expected label
     *
     * @return void
     */
    #[Test]
    #[DataProvider('lifetimeDescriptionDataProvider')]
    public function describesTheLifetimeWithTheTextsOfTheCaller(
        string $birthDate,
        string $deathDate,
        bool $isDead,
        string $expected,
    ): void {
        $labels = new LifetimeLabels('born %s', 'died %s', 'deceased');

        self::assertSame(
            $expected,
            $this->dateProcessorFor($birthDate, $deathDate, $isDead)->getLifetimeDescription($labels),
        );
    }

    /**
     * A person that the viewer may not see is never reported as deceased. webtrees decides from the
     * raw record that such a person is dead, for example while a keep-alive period hides a recent
     * death, and the dates of the person are hidden at the same time, so the lifetime label, the
     * compact label and the deceased flag stay neutral, as for any other hidden fact. A visible dead
     * person keeps its label, which the data providers of the other lifetime tests pin.
     *
     * @return void
     */
    #[Test]
    public function reportsAHiddenDeadPersonNeitherAsDeceasedNorWithASymbol(): void
    {
        $processor = new DateProcessor($this->individualWith(isDead: true, canShow: false));

        self::assertFalse($processor->isDead());
        self::assertSame('', $processor->getLifetimeDescription(new LifetimeLabels('born %s', 'died %s', 'deceased')));
        self::assertSame('', $processor->getCompactLifetimeDescription());
    }

    /**
     * The deceased flag of a visible person follows webtrees, so the gate only takes the flag away
     * from a person that the viewer may not see.
     *
     * @return void
     */
    #[Test]
    public function reportsAVisibleDeadPersonAsDeceased(): void
    {
        self::assertTrue((new DateProcessor($this->individualWith(isDead: true)))->isDead());
        self::assertFalse((new DateProcessor($this->individualWith(isDead: false)))->isDead());
    }

    /**
     * A visible person whose only death event is hidden from the viewer, for example by a
     * restriction on the fact, is still dead in the raw record. The viewer must not learn the
     * death from the labels, so the flag, the lifetime label and the compact label stay neutral.
     *
     * @return void
     */
    #[Test]
    public function reportsNoDeathOfAVisiblePersonWhoseDeathEventIsHidden(): void
    {
        $processor = new DateProcessor($this->individualWith(isDead: true, hasHiddenDeathEvent: true));

        self::assertFalse($processor->isDead());
        self::assertSame('', $processor->getLifetimeDescription(new LifetimeLabels('born %s', 'died %s', 'deceased')));
        self::assertSame('', $processor->getCompactLifetimeDescription());
    }

    /**
     * A death event the viewer may see keeps the deceased state, also when a further death event is
     * hidden, and so does a person whose dead state comes from the record without any death event.
     *
     * @return void
     */
    #[Test]
    public function keepsTheDeceasedStateWhenADeathEventIsVisibleOrNoneExists(): void
    {
        $visibleOnly = $this->individualWith(isDead: true, hasVisibleDeathEvent: true);
        $both        = $this->individualWith(isDead: true, hasVisibleDeathEvent: true, hasHiddenDeathEvent: true);
        $withoutAny  = $this->individualWith(isDead: true);

        self::assertTrue((new DateProcessor($visibleOnly))->isDead());
        self::assertTrue((new DateProcessor($both))->isDead());
        self::assertTrue((new DateProcessor($withoutAny))->isDead());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function presenceOfADateDataProvider(): array
    {
        // [ GEDCOM date, whether webtrees treats it as a usable date ]
        return [
            'a complete date'  => ['01 MAY 1853', true],
            'a qualified year' => ['ABT 1853', true],
            'no date at all'   => ['', false],
        ];
    }

    /**
     * The presence flags tell a consumer whether a birth or a death date exists. They must read
     * their own date and not the neighbouring one, so each is checked with the other date empty.
     *
     * @param string $input    The GEDCOM date
     * @param bool   $expected Whether the date counts as present
     *
     * @return void
     */
    #[Test]
    #[DataProvider('presenceOfADateDataProvider')]
    public function reportsWhetherABirthOrADeathDateIsPresent(string $input, bool $expected): void
    {
        self::assertSame($expected, $this->dateProcessorFor($input)->hasBirthDate());
        self::assertFalse($this->dateProcessorFor($input)->hasDeathDate());
        self::assertSame($expected, $this->dateProcessorFor('', $input)->hasDeathDate());
        self::assertFalse($this->dateProcessorFor('', $input)->hasBirthDate());
    }

    /**
     * @return array<string, array{string, int, int, string}>
     */
    public static function generationAwareDateDataProvider(): array
    {
        // [ GEDCOM date, generation, generations shown in full, expected text ]
        return [
            'inside the detail depth the full date shows' => ['12 MAY 1832', 1, 5, '12.05.1832'],
            'the last detailed generation still shows it' => ['12 MAY 1832', 5, 5, '12.05.1832'],
            'beyond the detail depth only the year shows' => ['12 MAY 1832', 6, 5, '1832'],
            'without a date nothing shows'                => ['', 1, 5, ''],
        ];
    }

    /**
     * The generation-aware birth and death dates show the full compact date up to the configured
     * depth and only the year beyond it, and nothing when the date is missing.
     *
     * @param string $input      The GEDCOM date
     * @param int    $generation The generation depth of the individual
     * @param int    $detailed   The generations that show the full date
     * @param string $expected   The expected text
     *
     * @return void
     */
    #[Test]
    #[DataProvider('generationAwareDateDataProvider')]
    public function showsTheBirthAndDeathDateByGenerationDepth(
        string $input,
        int $generation,
        int $detailed,
        string $expected,
    ): void {
        $birth = new DateProcessor($this->individualWith($input), $generation, $detailed, '%d.%m.%Y');
        $death = new DateProcessor($this->individualWith('', $input), $generation, $detailed, '%d.%m.%Y');

        self::assertSame($expected, $birth->getFormattedBirthDate());
        self::assertSame('', $birth->getFormattedDeathDate());
        self::assertSame($expected, $death->getFormattedDeathDate());
        self::assertSame('', $death->getFormattedBirthDate());
    }

    /**
     * @return array<string, array{string, bool, int, string}>
     */
    public static function marriageOfParentsDataProvider(): array
    {
        // [ GEDCOM marriage date, whether a MARR fact exists, generation, expected text ]
        return [
            'a dated marriage shows the full date'        => ['12 FEB 1850', true, 1, '12.02.1850'],
            'a deep generation shows only the year'       => ['12 FEB 1850', true, 7, '1850'],
            'no space is left beyond the last generation' => ['12 FEB 1850', true, 8, ''],
            'a marriage without a date shows the unknown' => ['', true, 1, '?'],
            'no marriage fact shows nothing'              => ['', false, 1, ''],
        ];
    }

    /**
     * The marriage date of the parents is formatted by generation depth, and a marriage that is
     * recorded without a usable date is told apart from no marriage at all.
     *
     * @param string $marriage    The GEDCOM marriage date of the parents
     * @param bool   $hasMarriage Whether the parents' family has a MARR fact
     * @param int    $generation  The generation depth of the individual
     * @param string $expected    The expected text
     *
     * @return void
     */
    #[Test]
    #[DataProvider('marriageOfParentsDataProvider')]
    public function formatsTheMarriageOfTheParentsByGenerationDepth(
        string $marriage,
        bool $hasMarriage,
        int $generation,
        string $expected,
    ): void {
        $individual = $this->individualWith(parents: $this->familyWith($marriage, $hasMarriage));
        $processor  = new DateProcessor($individual, $generation, PHP_INT_MAX, '%d.%m.%Y');

        self::assertSame($expected, $processor->getFormattedMarriageDateOfParents());
    }

    /**
     * An individual without a parent family has no marriage of parents to show.
     *
     * @return void
     */
    #[Test]
    public function showsNoMarriageOfParentsWithoutAParentFamily(): void
    {
        $processor = new DateProcessor($this->individualWith(), 1, PHP_INT_MAX, '%d.%m.%Y');

        self::assertSame('', $processor->getFormattedMarriageDateOfParents());
    }

    /**
     * Only the first parent family counts, so a later family does not replace the marriage of the
     * first one.
     *
     * @return void
     */
    #[Test]
    public function readsOnlyTheFirstParentFamilyForTheMarriageOfTheParents(): void
    {
        $individual = self::createStub(Individual::class);
        $individual->method('getBirthDate')->willReturn(new Date(''));
        $individual->method('getDeathDate')->willReturn(new Date(''));
        $individual->method('childFamilies')->willReturn(new Collection([
            $this->familyWith('', true),
            $this->familyWith('12 FEB 1850', true),
        ]));

        $processor = new DateProcessor($individual, 1, PHP_INT_MAX, '%d.%m.%Y');

        self::assertSame('?', $processor->getFormattedMarriageDateOfParents());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ownMarriageDataProvider(): array
    {
        // [ GEDCOM marriage date, expected text of the full compact date ]
        return [
            'a dated marriage shows the full date'    => ['12 FEB 1850', '12.02.1850'],
            'a marriage without a date shows nothing' => ['', ''],
        ];
    }

    /**
     * The own marriage of an individual is read from the first spouse family, once formatted by
     * generation depth and once always in full for a tooltip.
     *
     * @param string $marriage The GEDCOM marriage date of the first spouse family
     * @param string $expected The expected full compact date
     *
     * @return void
     */
    #[Test]
    #[DataProvider('ownMarriageDataProvider')]
    public function formatsTheOwnMarriageFromTheFirstSpouseFamily(string $marriage, string $expected): void
    {
        $individual = $this->individualWith(spouse: $this->familyWith($marriage, true));
        $processor  = new DateProcessor($individual, 1, PHP_INT_MAX, '%d.%m.%Y');

        self::assertSame($expected, $processor->getMarriageDateFull());
        self::assertSame($expected, $processor->getFormattedMarriageDate());
    }

    /**
     * Only the first spouse family counts, so a later family with a dated marriage does not
     * stand in for a first family without one. An individual without any spouse family has
     * nothing to show.
     *
     * @return void
     */
    #[Test]
    public function readsOnlyTheFirstSpouseFamilyForTheOwnMarriage(): void
    {
        $individual = self::createStub(Individual::class);
        $individual->method('getBirthDate')->willReturn(new Date(''));
        $individual->method('getDeathDate')->willReturn(new Date(''));
        $individual->method('spouseFamilies')->willReturn(new Collection([
            $this->familyWith('', true),
            $this->familyWith('12 FEB 1850', true),
        ]));

        $processor = new DateProcessor($individual, 1, PHP_INT_MAX, '%d.%m.%Y');

        self::assertSame('', $processor->getMarriageDateFull());
        self::assertSame('', $processor->getFormattedMarriageDate());
        self::assertSame('', (new DateProcessor($this->individualWith(), 1))->getMarriageDateFull());
    }

    /**
     * @return array<string, array{string, string, bool, string}>
     */
    public static function compactLifetimeDataProvider(): array
    {
        // [ GEDCOM birth date, GEDCOM death date, individual is dead, expected text ]
        return [
            'both years give the range'             => ['01 MAY 1853', '12 JUN 1933', true, "1853\u{2013}1933"],
            'a birth year alone gets the star'      => ['01 MAY 1853', '', false, "\u{2605} 1853"],
            'a death year alone gets the cross'     => ['', '12 JUN 1933', true, "\u{2020} 1933"],
            'a dead person without years the cross' => ['', '', true, "\u{2020}"],
            'a living person without years nothing' => ['', '', false, ''],
        ];
    }

    /**
     * The compact lifetime joins the years with a range dash, marks a lone year with the birth or
     * death symbol and a deceased person without years with the death symbol alone.
     *
     * @param string $birthDate The GEDCOM birth date
     * @param string $deathDate The GEDCOM death date
     * @param bool   $isDead    Whether webtrees considers the individual deceased
     * @param string $expected  The expected text
     *
     * @return void
     */
    #[Test]
    #[DataProvider('compactLifetimeDataProvider')]
    public function describesTheLifetimeInCompactForm(
        string $birthDate,
        string $deathDate,
        bool $isDead,
        string $expected,
    ): void {
        self::assertSame(
            $expected,
            $this->dateProcessorFor($birthDate, $deathDate, $isDead)->getCompactLifetimeDescription(),
        );
    }

    /**
     * Builds a DateProcessor the way a consumer does: around an individual, through
     * the real constructor. The individual is a stub because it is the collaborator,
     * not the unit under test — the constructor reads its birth and death dates.
     *
     * @param string $birthDate The GEDCOM birth date the individual reports
     * @param string $deathDate The GEDCOM death date the individual reports
     * @param bool   $isDead    Whether the individual is reported as deceased
     *
     * @return DateProcessor
     */
    private function dateProcessorFor(
        string $birthDate = '',
        string $deathDate = '',
        bool $isDead = false,
    ): DateProcessor {
        return new DateProcessor($this->individualWith($birthDate, $deathDate, $isDead));
    }

    /**
     * Builds the individual stub the processor reads. The family collections are empty unless a
     * family is given, because the processor takes the first family of each.
     *
     * @param string      $birthDate            The GEDCOM birth date the individual reports
     * @param string      $deathDate            The GEDCOM death date the individual reports
     * @param bool        $isDead               Whether the individual is reported as deceased
     * @param Family|null $spouse               The individual's own spouse family, if any
     * @param Family|null $parents              The family the individual is a child of, if any
     * @param bool        $canShow              Whether the viewer may see the individual
     * @param bool        $hasVisibleDeathEvent Whether a death event is visible to the viewer
     * @param bool        $hasHiddenDeathEvent  Whether a death event exists that the viewer may not see
     *
     * @return Individual
     */
    private function individualWith(
        string $birthDate = '',
        string $deathDate = '',
        bool $isDead = false,
        ?Family $spouse = null,
        ?Family $parents = null,
        bool $canShow = true,
        bool $hasVisibleDeathEvent = false,
        bool $hasHiddenDeathEvent = false,
    ): Individual {
        $individual = self::createStub(Individual::class);
        $individual->method('canShow')->willReturn($canShow);
        $individual->method('getBirthDate')->willReturn(new Date($birthDate));
        $individual->method('getDeathDate')->willReturn(new Date($deathDate));
        $individual->method('isDead')->willReturn($isDead);
        $individual->method('facts')->willReturnCallback(
            static function (array $filter = [], bool $sort = false, ?int $accessLevel = null) use ($hasVisibleDeathEvent, $hasHiddenDeathEvent): Collection {
                $events = [];

                if ($filter !== Gedcom::DEATH_EVENTS) {
                    return new Collection($events);
                }

                if ($hasVisibleDeathEvent) {
                    $events[] = self::createStub(Fact::class);
                }

                if ($hasHiddenDeathEvent && ($accessLevel === Auth::PRIV_HIDE)) {
                    $events[] = self::createStub(Fact::class);
                }

                return new Collection($events);
            }
        );
        $individual->method('spouseFamilies')->willReturn(new Collection($spouse instanceof Family ? [$spouse] : []));
        $individual->method('childFamilies')->willReturn(new Collection($parents instanceof Family ? [$parents] : []));

        return $individual;
    }

    /**
     * Builds a family stub with a marriage date and, optionally, a MARR fact.
     *
     * @param string $marriageDate The GEDCOM marriage date of the family
     * @param bool   $hasMarriage  Whether the family carries a MARR fact
     *
     * @return Family
     */
    private function familyWith(string $marriageDate, bool $hasMarriage): Family
    {
        $family = self::createStub(Family::class);
        $family->method('getMarriageDate')->willReturn(new Date($marriageDate));
        $family->method('facts')->willReturn(new Collection($hasMarriage ? [self::createStub(Fact::class)] : []));

        return $family;
    }

    /**
     * Asserts the value is plain text. The webtrees date accessors this wraps can
     * return markup, and the value is consumed by templates that escape it, so any
     * leaking tag would reach the user verbatim.
     *
     * @param string $result The value to check
     *
     * @return void
     */
    private function assertPlainText(string $result): void
    {
        // Anchor the positive case first: stripping tags from an empty string also
        // yields an empty string, so the markup assertion alone stays green if the
        // accessor regresses to returning nothing at all.
        self::assertNotSame('', $result, 'a parseable date must render something');
        self::assertSame(strip_tags($result), $result, 'no markup may reach the template');
    }

    /**
     * Tests extracting the birth year from a date.
     *
     * @param string $input
     * @param int    $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('yearDataProvider')]
    public function extractsTheYearFromAPlainBirthDateAndFromARange(string $input, int $expected): void
    {
        self::assertSame($expected, $this->dateProcessorFor($input)->getBirthYear());
    }

    /**
     * Tests extracting the death year from a date.
     *
     * @param string $input
     * @param int    $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('yearDataProvider')]
    public function extractsTheYearFromAPlainDeathDateAndFromARange(string $input, int $expected): void
    {
        self::assertSame($expected, $this->dateProcessorFor('', $input)->getDeathYear());
    }

    /**
     * @return string[][]
     */
    public static function dateDataProvider(): array
    {
        // [ GEDCOM date input ] — the assertion is shape-only, see assertPlainText()
        return [
            [
                '01 MAY 2000',
            ],
        ];
    }

    /**
     * Tests extracting the plain birthdate from a date.
     *
     * @param string $input
     *
     * @return void
     */
    #[Test]
    #[DataProvider('dateDataProvider')]
    public function rendersTheBirthDateAsPlainTextWithoutMarkup(string $input): void
    {
        I18N::init('en-US', true);

        $this->assertPlainText($this->dateProcessorFor($input)->getBirthDate());
    }

    /**
     * Tests extracting the plain death date from a date.
     *
     * @param string $input
     *
     * @return void
     */
    #[Test]
    #[DataProvider('dateDataProvider')]
    public function rendersTheDeathDateAsPlainTextWithoutMarkup(string $input): void
    {
        I18N::init('en-US', true);

        $this->assertPlainText($this->dateProcessorFor('', $input)->getDeathDate());
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function marriageFormatDataProvider(): array
    {
        // [ generation, compactDateFormat, expected ]
        return [
            'german default within detail depth' => [1, '%d.%m.%Y', '12.02.1850'],
            'us order within detail depth'       => [1, '%m/%d/%Y', '02/12/1850'],
            'iso order within detail depth'      => [1, '%Y-%m-%d', '1850-02-12'],
            'year only beyond detail depth'      => [7, '%m/%d/%Y', '1850'],
        ];
    }

    /**
     * The compact marriage date honours the caller-supplied locale-aware format on the
     * full-date branch, while the year-only branch stays format-independent.
     *
     * @param int    $generation
     * @param string $compactDateFormat
     * @param string $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('marriageFormatDataProvider')]
    public function formatMarriageDateHonoursCompactFormat(
        int $generation,
        string $compactDateFormat,
        string $expected,
    ): void {
        $result = DateProcessor::formatMarriageDate(
            new Date('12 FEB 1850'),
            $generation,
            PHP_INT_MAX,
            $compactDateFormat,
        );

        self::assertSame($expected, $result);
    }

    /**
     * The constructor's compactDateFormat parameter is threaded through the
     * instance methods (here getBirthDateFull()), not only the static
     * formatMarriageDate().
     *
     * @return void
     */
    #[Test]
    public function constructorCompactDateFormatIsUsedByInstanceMethods(): void
    {
        $individual = self::createStub(Individual::class);
        $individual->method('getBirthDate')->willReturn(new Date('12 FEB 1850'));
        $individual->method('getDeathDate')->willReturn(new Date(''));

        $processor = new DateProcessor($individual, 1, PHP_INT_MAX, '%Y-%m-%d');

        self::assertSame('1850-02-12', $processor->getBirthDateFull());
    }
}
