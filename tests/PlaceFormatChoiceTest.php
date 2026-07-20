<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use MagicSunday\Webtrees\ModuleBase\Model\PlaceFormatChoice;
use MagicSunday\Webtrees\ModuleBase\Model\PlaceFormatSpec;
use MagicSunday\Webtrees\ModuleBase\Model\PlaceStyle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_map;
use function sort;

/**
 * Locks the {@see PlaceFormatChoice} contract: every choice resolves into the
 * correct {@see PlaceFormatSpec} via toSpec(), Automatic collapses to
 * PlaceStyle::Full for a non-positive tree level count (unset preference or
 * the pre-3.0 sentinel), and the persisted backing values round-trip for
 * every case.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(PlaceFormatChoice::class)]
#[UsesClass(PlaceFormatSpec::class)]
#[UsesClass(PlaceStyle::class)]
final class PlaceFormatChoiceTest extends TestCase
{
    /**
     * Each selectable choice resolves into a concrete formatter instruction. The
     * tree arguments apply to "Automatic" only; every other case ignores them,
     * which the deliberately conspicuous 7/true arguments make visible.
     *
     * @param PlaceFormatChoice $choice          Choice under test
     * @param PlaceStyle        $expectedStyle   Expected resolved style
     * @param int               $expectedLevels  Expected resolved level count
     * @param bool              $expectedFromEnd Expected resolved fromEnd flag
     *
     * @return void
     */
    #[Test]
    #[DataProvider('specProvider')]
    public function choicesResolveIntoFormatterInstructions(
        PlaceFormatChoice $choice,
        PlaceStyle $expectedStyle,
        int $expectedLevels,
        bool $expectedFromEnd,
    ): void {
        $spec = $choice->toSpec(7, true);

        self::assertSame($expectedStyle, $spec->style);
        self::assertSame($expectedLevels, $spec->levels);
        self::assertSame($expectedFromEnd, $spec->fromEnd);
    }

    /**
     * @return array<string, array{0: PlaceFormatChoice, 1: PlaceStyle, 2: int, 3: bool}>
     */
    public static function specProvider(): array
    {
        return [
            'automatic adopts the tree settings' => [PlaceFormatChoice::Automatic, PlaceStyle::Levels, 7, true],
            'full name'                          => [PlaceFormatChoice::Full, PlaceStyle::Full, 0, false],
            'one level'                          => [PlaceFormatChoice::Levels1, PlaceStyle::Levels, 1, false],
            'two levels'                         => [PlaceFormatChoice::Levels2, PlaceStyle::Levels, 2, false],
            'three levels'                       => [PlaceFormatChoice::Levels3, PlaceStyle::Levels, 3, false],
            'place and country'                  => [PlaceFormatChoice::CityCountry, PlaceStyle::CityCountry, 0, false],
        ];
    }

    /**
     * A tree configured for zero levels means "show everything", not "show
     * nothing" — and a negative value (the pre-3.0 automatic sentinel, still
     * present in databases) must not reach the spec, which rejects it.
     *
     * @param int $treeLevels Tree-preference level count fed into PlaceFormatChoice::Automatic->toSpec()
     *
     * @return void
     */
    #[Test]
    #[DataProvider('degenerateTreeLevelProvider')]
    public function nonPositiveTreeLevelsMeanTheFullName(int $treeLevels): void
    {
        $spec = PlaceFormatChoice::Automatic->toSpec($treeLevels, false);

        self::assertSame(PlaceStyle::Full, $spec->style);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function degenerateTreeLevelProvider(): array
    {
        return [
            'unset preference reads as zero' => [0],
            'legacy automatic sentinel'      => [-1],
            'arbitrary negative value'       => [-7],
        ];
    }

    /**
     * The backing values are persisted in a module preference, so changing one
     * silently resets every installation that stored it. Order is NOT part of
     * that contract, so this checks the round-trip rather than a sequence.
     *
     * @param string            $stored   Backing value as persisted in the module preference
     * @param PlaceFormatChoice $expected Expected enum case for the stored value
     *
     * @return void
     */
    #[Test]
    #[DataProvider('storedValueProvider')]
    public function storedValuesRoundTripBackToTheirCase(string $stored, PlaceFormatChoice $expected): void
    {
        self::assertSame($expected, PlaceFormatChoice::tryFrom($stored));
    }

    /**
     * @return array<string, array{0: string, 1: PlaceFormatChoice}>
     */
    public static function storedValueProvider(): array
    {
        return [
            'auto'         => ['auto', PlaceFormatChoice::Automatic],
            'full'         => ['full', PlaceFormatChoice::Full],
            'levels-1'     => ['levels-1', PlaceFormatChoice::Levels1],
            'levels-2'     => ['levels-2', PlaceFormatChoice::Levels2],
            'levels-3'     => ['levels-3', PlaceFormatChoice::Levels3],
            'city-country' => ['city-country', PlaceFormatChoice::CityCountry],
        ];
    }

    /**
     * The provider above must list every case — otherwise a newly added backing
     * value drops out of the persistence contract unnoticed. Comparing against
     * cases() keeps this self-maintaining; a bare count would pass whenever the
     * two happened to change together, and would miss a renamed value entirely.
     * Order is NOT part of the contract (see {@see self::storedValuesRoundTripBackToTheirCase()}),
     * so both sides are sorted before comparison.
     *
     * @return void
     */
    #[Test]
    public function theStoredValueProviderCoversEveryCase(): void
    {
        $caseValues     = array_map(static fn (PlaceFormatChoice $case): string => $case->value, PlaceFormatChoice::cases());
        $providedValues = array_column(self::storedValueProvider(), 0);

        sort($caseValues);
        sort($providedValues);

        self::assertSame($caseValues, $providedValues);
    }
}
