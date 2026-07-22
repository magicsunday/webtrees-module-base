<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Support;

use MagicSunday\Webtrees\ModuleBase\Support\TextDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Locks the RTL verdict the chart modules use to orient a label: the first
 * character that falls into a recognised script range decides, never the
 * majority — characters outside every known range are skipped on the way.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(TextDirection::class)]
final class TextDirectionTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function scriptDirectionDataProvider(): array
    {
        // [ text, expected isRtl ]
        return [
            'Latin text reads left-to-right' => [
                'Hello world',
                false,
            ],
            'Hebrew text reads right-to-left' => [
                'שלום עולם',
                true,
            ],
            'Arabic text reads right-to-left' => [
                'مرحبا بالعالم',
                true,
            ],
            'Digits alone are not right-to-left' => [
                '123',
                false,
            ],
            'An empty string is not right-to-left' => [
                '',
                false,
            ],

            // The FIRST character in a recognised script range decides; later
            // runs are never counted. Both rows below would come out the other
            // way round under a majority rule, so they are what pins the
            // first-strong contract the chart labels depend on.
            'A leading Latin letter decides even when Hebrew dominates' => [
                'A שלום שלום שלום',
                false,
            ],
            'A leading Hebrew letter decides even when Latin dominates' => [
                'שלום Hello',
                true,
            ],

            // Characters in no recognised range are skipped rather than
            // treated as a script of their own: the umlaut is passed over and
            // the following "l" settles it, and the digits and punctuation in
            // front of a Hebrew name never make the label left-to-right.
            'A leading diacritic is skipped, not treated as a script' => [
                'Ölbaum',
                false,
            ],
            'Leading digits and punctuation are skipped before the script decides' => [
                '1. שלום',
                true,
            ],
        ];
    }

    /**
     * Pins the RTL verdict the chart modules use to orient a label.
     *
     * @param string $text
     * @param bool   $expected
     */
    #[Test]
    #[DataProvider('scriptDirectionDataProvider')]
    public function reportsWhetherTheTextReadsRightToLeft(string $text, bool $expected): void
    {
        self::assertSame($expected, TextDirection::isRtl($text));
    }

    /**
     * The helper is a static-only utility the chart modules call without an
     * instance, so it must stay closed for subclassing. Everything else about
     * the signature is already proven by the behavioural calls above.
     */
    #[Test]
    public function theHelperClassIsFinal(): void
    {
        self::assertTrue((new ReflectionClass(TextDirection::class))->isFinal());
    }
}
