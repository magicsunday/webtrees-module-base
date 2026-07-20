<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use InvalidArgumentException;
use MagicSunday\Webtrees\ModuleBase\Model\PlaceFormatSpec;
use MagicSunday\Webtrees\ModuleBase\Model\PlaceStyle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function preg_quote;

/**
 * Locks the {@see PlaceFormatSpec} contract: the constructor accepts every
 * valid (style, levels) combination and rejects a negative level count for
 * any style, or a zero level count specifically for PlaceStyle::Levels, with
 * a descriptive exception message.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(PlaceFormatSpec::class)]
#[UsesClass(PlaceStyle::class)]
final class PlaceFormatSpecTest extends TestCase
{
    /**
     * A level count of zero for PlaceStyle::Full, and a level count of one for
     * PlaceStyle::Levels, are both valid boundary values and must not throw.
     * PlaceStyle::CityCountry ignores the level count entirely, so zero is
     * valid there too — {@see \MagicSunday\Webtrees\ModuleBase\Model\PlaceFormatChoice::toSpec()}
     * relies on exactly that to construct the spec without a level argument.
     *
     * @param PlaceStyle $style  Style under test
     * @param int        $levels Level count expected to be accepted
     *
     * @return void
     */
    #[Test]
    #[DataProvider('validLevelCountProvider')]
    public function aValidLevelCountIsAccepted(PlaceStyle $style, int $levels): void
    {
        $spec = new PlaceFormatSpec($style, $levels);

        self::assertSame($levels, $spec->levels);
    }

    /**
     * @return array<string, array{0: PlaceStyle, 1: int}>
     */
    public static function validLevelCountProvider(): array
    {
        return [
            'zero is the only sensible value for Full' => [PlaceStyle::Full, 0],
            'one is the minimum for Levels'            => [PlaceStyle::Levels, 1],
            'zero is valid for CityCountry'            => [PlaceStyle::CityCountry, 0],
        ];
    }

    /**
     * A negative level count would silently invert Place::firstParts(), which
     * drops the LAST segment instead of keeping the first N. The tree preference
     * feeding this value is an unvalidated database string, and "-1" is the old
     * automatic sentinel that really does sit in existing installations.
     *
     * A zero level count for PlaceStyle::Levels is the removed pre-3.0 sentinel
     * one layer down: PlaceStyle::Full now owns the "no truncation" meaning, so
     * PlaceStyle::Levels combined with zero levels has no correct interpretation
     * and must be rejected at construction rather than reinterpreted later.
     *
     * @param PlaceStyle $style           Style under test
     * @param int        $levels          Level count expected to be rejected
     * @param string     $expectedMessage Substring expected in the exception message
     *
     * @return void
     */
    #[Test]
    #[DataProvider('invalidLevelCountProvider')]
    public function anInvalidLevelCountIsRejected(PlaceStyle $style, int $levels, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($expectedMessage, '/') . '/');

        new PlaceFormatSpec($style, $levels);
    }

    /**
     * @return array<string, array{0: PlaceStyle, 1: int, 2: string}>
     */
    public static function invalidLevelCountProvider(): array
    {
        return [
            'negative is rejected for Full'        => [PlaceStyle::Full, -1, 'must not be negative'],
            'negative is rejected for Levels'      => [PlaceStyle::Levels, -1, 'must not be negative'],
            'negative is rejected for CityCountry' => [PlaceStyle::CityCountry, -1, 'must not be negative'],
            'zero is rejected for Levels'          => [PlaceStyle::Levels, 0, 'PlaceStyle::Levels requires at least one level'],
        ];
    }
}
