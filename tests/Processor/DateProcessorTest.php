<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Processor;

use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Date\AbstractCalendarDate;
use Fisharebest\Webtrees\Factories\CacheFactory;
use Fisharebest\Webtrees\Factories\CalendarDateFactory;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use MagicSunday\Webtrees\ModuleBase\Processor\DateProcessor;
use MagicSunday\Webtrees\ModuleBase\Support\PlainText;
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

        Registry::cache(new CacheFactory());
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
                'CAL 30 NOV 2000',
                2000,
            ],
            [
                'BET SEP 2000 AND AUG 2001',
                2000,
            ],
            [
                'BET @#DJULIAN@ 01 SEP 1700 AND @#DGREGORIAN@ 30 SEP 1753',
                1700,
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
            'an empty date gives nothing'                 => ['', '%d.%m.%Y', ''],
            'an unparseable date gives nothing'           => ['not a date', '%d.%m.%Y', ''],
            'a complete date uses the compact format'     => ['12 MAY 1832', '%d.%m.%Y', '12.05.1832'],
            'a complete date follows the given pattern'   => ['12 MAY 1832', '%Y/%m/%d', '1832/05/12'],
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
     * Builds a DateProcessor the way a consumer does: around an individual, through
     * the real constructor. The individual is a stub because it is the collaborator,
     * not the unit under test — the constructor reads its birth and death dates.
     *
     * @param string $birthDate The GEDCOM birth date the individual reports
     * @param string $deathDate The GEDCOM death date the individual reports
     *
     * @return DateProcessor
     */
    private function dateProcessorFor(string $birthDate = '', string $deathDate = ''): DateProcessor
    {
        $individual = self::createStub(Individual::class);
        $individual->method('getBirthDate')->willReturn(new Date($birthDate));
        $individual->method('getDeathDate')->willReturn(new Date($deathDate));

        return new DateProcessor($individual);
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
    public function extractsTheYearFromEveryGedcomBirthDateForm(string $input, int $expected): void
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
    public function extractsTheYearFromEveryGedcomDeathDateForm(string $input, int $expected): void
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
            [
                'CAL 30 NOV 2000',
            ],
            [
                'BET SEP 2000 AND AUG 2001',
            ],
            [
                'BET @#DJULIAN@ 01 SEP 1700 AND @#DGREGORIAN@ 30 SEP 1753',
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
