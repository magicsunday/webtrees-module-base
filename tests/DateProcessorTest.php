<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Factories\CacheFactory;
use Fisharebest\Webtrees\Factories\CalendarDateFactory;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use MagicSunday\Webtrees\ModuleBase\Processor\DateProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

/**
 * DateProcessorTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
class DateProcessorTest extends TestCase
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
     * Tests extracting the year from a date.
     *
     * @param int    $expected
     * @param string $input
     * @param string $propertyName
     * @param string $methodeName
     *
     * @return void
     *
     * @throws ReflectionException
     */
    public function assertExtractedYear(
        int $expected,
        string $input,
        string $propertyName,
        string $methodeName,
    ): void {
        // Create mock
        $dateProcessorMock = self::createStub(DateProcessor::class);

        $reflectionClass    = new ReflectionClass(DateProcessor::class);
        $reflectionProperty = $reflectionClass->getProperty($propertyName);
        $reflectionProperty->setValue($dateProcessorMock, new Date($input));

        $result = $reflectionClass->getMethod($methodeName)
            ->invokeArgs($dateProcessorMock, []);

        self::assertSame($expected, $result);
    }

    /**
     * Tests extracting the birth year from a date.
     *
     * @param string $input
     * @param int    $expected
     *
     * @return void
     *
     * @throws ReflectionException
     */
    #[Test]
    #[DataProvider('yearDataProvider')]
    public function getBirthYear(string $input, int $expected): void
    {
        $this->assertExtractedYear(
            $expected,
            $input,
            'birthDate',
            'getBirthYear'
        );
    }

    /**
     * Tests extracting the death year from a date.
     *
     * @param string $input
     * @param int    $expected
     *
     * @return void
     *
     * @throws ReflectionException
     */
    #[Test]
    #[DataProvider('yearDataProvider')]
    public function getDeathYear(string $input, int $expected): void
    {
        $this->assertExtractedYear(
            $expected,
            $input,
            'deathDate',
            'getDeathYear'
        );
    }

    /**
     * @return string[][]
     */
    public static function dateDataProvider(): array
    {
        // [ input, expected ]
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
     * Tests that the date value does not contain HTML tags.
     *
     * @param string $input
     * @param string $propertyName
     * @param string $methodeName
     *
     * @return void
     *
     * @throws ReflectionException
     */
    public function assertDateNotContainsHtml(
        string $input,
        string $propertyName,
        string $methodeName,
    ): void {
        // Create mock
        $dateProcessorMock = self::createStub(DateProcessor::class);

        $reflectionClass    = new ReflectionClass(DateProcessor::class);
        $reflectionProperty = $reflectionClass->getProperty($propertyName);
        $reflectionProperty->setValue($dateProcessorMock, new Date($input));

        $result = $reflectionClass->getMethod($methodeName)
            ->invokeArgs($dateProcessorMock, []);

        self::assertIsString($result);
        self::assertSame(strip_tags($result), $result);
    }

    /**
     * Tests extracting the plain birthdate from a date.
     *
     * @param string $input
     *
     * @return void
     *
     * @throws ReflectionException
     */
    #[Test]
    #[DataProvider('dateDataProvider')]
    public function getBirthDate(string $input): void
    {
        I18N::init('en-US', true);

        $this->assertDateNotContainsHtml(
            $input,
            'birthDate',
            'getBirthDate'
        );
    }

    /**
     * Tests extracting the plain death date from a date.
     *
     * @param string $input
     *
     * @return void
     *
     * @throws ReflectionException
     */
    #[Test]
    #[DataProvider('dateDataProvider')]
    public function getDeathDate(string $input): void
    {
        I18N::init('en-US', true);

        $this->assertDateNotContainsHtml(
            $input,
            'deathDate',
            'getDeathDate'
        );
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
