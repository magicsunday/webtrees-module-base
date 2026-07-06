<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use MagicSunday\Webtrees\ModuleBase\Support\CompactDateFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function extension_loaded;

/**
 * CompactDateFormatTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(CompactDateFormat::class)]
class CompactDateFormatTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function localeDataProvider(): array
    {
        // [ locale tag, expected webtrees compact format ]
        return [
            'german day-month-year, dots' => ['de', '%d.%m.%Y'],
            'us month-day-year, slashes'  => ['en-US', '%n/%j/%Y'],
            'british day-month-year'      => ['en-GB', '%d/%m/%Y'],
            'french day-month-year'       => ['fr', '%d/%m/%Y'],
            'japanese year-month-day'     => ['ja', '%Y/%m/%d'],
            'swedish iso year-month-day'  => ['sv', '%Y-%m-%d'],
        ];
    }

    /**
     * The ICU short pattern of a locale is translated into the matching webtrees
     * compact format with a four-digit year.
     *
     * @param string $localeTag
     * @param string $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('localeDataProvider')]
    public function forLocaleDerivesWebtreesFormat(string $localeTag, string $expected): void
    {
        if (!extension_loaded('intl')) {
            self::markTestSkipped('ext-intl is not loaded');
        }

        self::assertSame($expected, CompactDateFormat::forLocale($localeTag));
    }

    /**
     * The year is always four digits, even though several locales default to a
     * two-digit short year in ICU.
     *
     * @return void
     */
    #[Test]
    public function forLocaleAlwaysWidensTheYear(): void
    {
        if (!extension_loaded('intl')) {
            self::markTestSkipped('ext-intl is not loaded');
        }

        // "de" ships a two-digit ICU short year (dd.MM.yy) that must be widened.
        self::assertStringContainsString('%Y', CompactDateFormat::forLocale('de'));
        self::assertStringNotContainsString('%y', CompactDateFormat::forLocale('de'));
    }

    /**
     * An unparseable locale tag (a NUL byte, which ICU rejects with a ValueError)
     * falls back to the FALLBACK format. Deliberately NOT guarded by the ext-intl
     * skip: with intl present the ValueError branch is exercised, with intl absent
     * the early extension_loaded() branch returns the same FALLBACK — so this pins
     * the FALLBACK contract on every CI leg, including one without ext-intl.
     *
     * @return void
     */
    #[Test]
    public function forUnparseableLocaleUsesTheFallbackFormat(): void
    {
        self::assertSame(CompactDateFormat::FALLBACK, CompactDateFormat::forLocale("a\0b"));
    }

    /**
     * An empty locale tag falls back to the FALLBACK format rather than being passed
     * to IntlDateFormatter, which would resolve the host's default locale.
     *
     * @return void
     */
    #[Test]
    public function forEmptyLocaleUsesTheFallbackFormat(): void
    {
        self::assertSame(CompactDateFormat::FALLBACK, CompactDateFormat::forLocale(''));
    }
}
