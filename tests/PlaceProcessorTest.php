<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Place;
use Illuminate\Support\Collection;
use MagicSunday\Webtrees\ModuleBase\Processor\PlaceProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_slice;

/**
 * PlaceProcessorTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(PlaceProcessor::class)]
class PlaceProcessorTest extends TestCase
{
    /**
     * Builds a Place stub whose firstParts()/lastParts() actually honour the count
     * argument by slicing the real part list. This locks BOTH the placeParts value
     * the processor passes to the seam and the first-vs-last selection — a stub that
     * returned a fixed pre-truncated collection would green even if the processor
     * passed a wrong count.
     *
     * @param string        $gedcomName
     * @param array<string> $parts
     *
     * @return Place
     */
    private function placeStub(string $gedcomName, array $parts): Place
    {
        $place = self::createStub(Place::class);
        $place->method('gedcomName')->willReturn($gedcomName);
        $place->method('firstParts')->willReturnCallback(
            static fn (int $count): Collection => new Collection(array_slice($parts, 0, $count))
        );
        $place->method('lastParts')->willReturnCallback(
            static fn (int $count): Collection => new Collection(array_slice($parts, -$count))
        );

        return $place;
    }

    /**
     * The default (no suffix) keeps the first parts, i.e. the locality end.
     *
     * @return void
     */
    #[Test]
    public function shortPlaceNameWithoutSuffixKeepsFirstParts(): void
    {
        $processor = new PlaceProcessor(self::createStub(Individual::class), 2);

        $place = $this->placeStub('Mitte, Berlin, Germany', ['Mitte', 'Berlin', 'Germany']);

        self::assertSame('Mitte, Berlin', $processor->shortPlaceName($place));
    }

    /**
     * With the suffix flag the last parts (country end) are kept instead.
     *
     * @return void
     */
    #[Test]
    public function shortPlaceNameWithSuffixKeepsLastParts(): void
    {
        $processor = new PlaceProcessor(self::createStub(Individual::class), 2, true);

        $place = $this->placeStub('Mitte, Berlin, Germany', ['Mitte', 'Berlin', 'Germany']);

        self::assertSame('Berlin, Germany', $processor->shortPlaceName($place));
    }

    /**
     * A part count of 0 returns the untouched full place name regardless of the suffix flag.
     *
     * @return void
     */
    #[Test]
    public function shortPlaceNameWithZeroPartsReturnsFullName(): void
    {
        $processor = new PlaceProcessor(self::createStub(Individual::class), 0, true);

        $place = $this->placeStub('Mitte, Berlin, Germany', ['Mitte', 'Berlin', 'Germany']);

        self::assertSame('Mitte, Berlin, Germany', $processor->shortPlaceName($place));
    }

    /**
     * An empty GEDCOM place name yields an empty result.
     *
     * @return void
     */
    #[Test]
    public function shortPlaceNameWithEmptyPlaceReturnsEmptyString(): void
    {
        $processor = new PlaceProcessor(self::createStub(Individual::class), 2);

        $place = $this->placeStub('', []);

        self::assertSame('', $processor->shortPlaceName($place));
    }
}
