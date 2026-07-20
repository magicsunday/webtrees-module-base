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
use MagicSunday\Webtrees\ModuleBase\Model\PlaceFormatSpec;
use MagicSunday\Webtrees\ModuleBase\Model\PlaceStyle;
use MagicSunday\Webtrees\ModuleBase\Processor\PlaceProcessor;
use MagicSunday\Webtrees\ModuleBase\Support\Locale\IsoCountryMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
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
#[UsesClass(PlaceFormatSpec::class)]
#[UsesClass(PlaceStyle::class)]
#[UsesClass(IsoCountryMap::class)]
class PlaceProcessorTest extends TestCase
{
    /**
     * The resolver memoises into process-wide statics, and the first instance
     * burns its locale into the shared map; without a reset the outcome would
     * depend on test order.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        IsoCountryMap::clearCache();
    }

    /**
     * Builds a Place stub whose firstParts()/lastParts() actually honour the count
     * argument by slicing the real part list. This locks BOTH the $this->format->levels
     * value the processor passes to the seam and the first-vs-last selection — a stub
     * that returned a fixed pre-truncated collection would green even if the processor
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
        $processor = new PlaceProcessor(
            self::createStub(Individual::class),
            new PlaceFormatSpec(PlaceStyle::Levels, 2),
            new IsoCountryMap('en_US')
        );

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
        $processor = new PlaceProcessor(
            self::createStub(Individual::class),
            new PlaceFormatSpec(PlaceStyle::Levels, 2, true),
            new IsoCountryMap('en_US')
        );

        $place = $this->placeStub('Mitte, Berlin, Germany', ['Mitte', 'Berlin', 'Germany']);

        self::assertSame('Berlin, Germany', $processor->shortPlaceName($place));
    }

    /**
     * The Full style returns the untouched recorded name.
     *
     * @return void
     */
    #[Test]
    public function fullStyleReturnsTheRecordedName(): void
    {
        $processor = new PlaceProcessor(
            self::createStub(Individual::class),
            new PlaceFormatSpec(PlaceStyle::Full),
            new IsoCountryMap('en_US')
        );

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
        $processor = new PlaceProcessor(
            self::createStub(Individual::class),
            new PlaceFormatSpec(PlaceStyle::Levels, 1),
            new IsoCountryMap('en_US')
        );

        $place = $this->placeStub('', []);

        self::assertSame('', $processor->shortPlaceName($place));
    }

    /**
     * The tree default of nine levels exceeds any real hierarchy, so it shows the
     * whole name — but only the paired three-level case proves the processor is
     * slicing at all rather than always returning the recorded name.
     *
     * @param int    $levels
     * @param string $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('treeLevelProvider')]
    public function levelStyleSlicesFromTheLocalityEnd(int $levels, string $expected): void
    {
        // The recorded name deliberately lacks the spaces the expectations carry,
        // so an implementation that returns gedcomName() unchanged once the level
        // count exceeds the segment count fails the nine-level row.
        $place = $this->placeStub(
            'Hamburg,Wandsbek,Schleswig-Holstein,Deutschland',
            ['Hamburg', 'Wandsbek', 'Schleswig-Holstein', 'Deutschland']
        );

        $individual = self::createStub(Individual::class);
        $individual->method('getBirthPlace')->willReturn($place);

        $processor = new PlaceProcessor(
            $individual,
            new PlaceFormatSpec(PlaceStyle::Levels, $levels),
            new IsoCountryMap('en_US')
        );

        self::assertSame($expected, $processor->getBirthPlaceShort());
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function treeLevelProvider(): array
    {
        return [
            'webtrees default of nine keeps everything' => [
                9, 'Hamburg, Wandsbek, Schleswig-Holstein, Deutschland',
            ],
            'three levels drop the country' => [
                3, 'Hamburg, Wandsbek, Schleswig-Holstein',
            ],
            'one level keeps the locality' => [
                1, 'Hamburg',
            ],
        ];
    }

    /**
     * The place-and-country style keeps the outermost segments and drops
     * everything between them.
     *
     * @param string        $gedcomName
     * @param array<string> $parts
     * @param string        $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('cityCountryProvider')]
    public function placeAndCountryKeepsTheOutermostSegments(
        string $gedcomName,
        array $parts,
        string $expected,
    ): void {
        $individual = self::createStub(Individual::class);
        $individual->method('getBirthPlace')->willReturn($this->placeStub($gedcomName, $parts));

        $processor = new PlaceProcessor(
            $individual,
            new PlaceFormatSpec(PlaceStyle::CityCountry),
            new IsoCountryMap('en_US')
        );

        self::assertSame($expected, $processor->getBirthPlaceShort());
    }

    /**
     * The gedcomName deliberately differs from the joined parts (no space after
     * the comma) wherever the expected output equals the whole place: without
     * that, a plain pass-through implementation would pass too.
     *
     * @return array<string, array{0: string, 1: array<string>, 2: string}>
     */
    public static function cityCountryProvider(): array
    {
        return [
            'four segments collapse to two' => [
                'Hamburg, Wandsbek, Schleswig-Holstein, Deutschland',
                ['Hamburg', 'Wandsbek', 'Schleswig-Holstein', 'Deutschland'],
                'Hamburg, Deutschland',
            ],
            'two segments are rebuilt, not passed through' => [
                'Berlin,Deutschland',
                ['Berlin', 'Deutschland'],
                'Berlin, Deutschland',
            ],
            'a single segment is left whole' => [
                'Berlin',
                ['Berlin'],
                'Berlin',
            ],
            'an alpha-3 code is spelled out' => [
                'Hamburg, DEU',
                ['Hamburg', 'DEU'],
                'Hamburg, Germany',
            ],
            'a Chapman home-nation code folds onto the state' => [
                'London, ENG',
                ['London', 'ENG'],
                'London, United Kingdom',
            ],
            'a two-letter segment is never expanded' => [
                'Dover, DE',
                ['Dover', 'DE'],
                'Dover, DE',
            ],
            'a German state abbreviation survives' => [
                'Ulm, BW',
                ['Ulm', 'BW'],
                'Ulm, BW',
            ],
            'an unresolvable three-letter token stays put' => [
                'Ulm, XYZ',
                ['Ulm', 'XYZ'],
                'Ulm, XYZ',
            ],
            'a plain country name is not translated' => [
                'Hamburg, Deutschland',
                ['Hamburg', 'Deutschland'],
                'Hamburg, Deutschland',
            ],
            'a longer unresolvable segment stays put' => [
                'London, Middlesex',
                ['London', 'Middlesex'],
                'London, Middlesex',
            ],
            'a place with no usable segment yields nothing' => [
                'Foo',
                [],
                '',
            ],
        ];
    }

    /**
     * Country names follow the interface language: the same record renders
     * "Germany" for an English user and "Deutschland" for a German one. This is
     * the only output path of the feature that depends on the locale.
     *
     * @return void
     */
    #[Test]
    public function spelledOutCountriesFollowTheUserLocale(): void
    {
        $individual = self::createStub(Individual::class);
        $individual->method('getBirthPlace')
            ->willReturn($this->placeStub('Hamburg, DEU', ['Hamburg', 'DEU']));

        $processor = new PlaceProcessor(
            $individual,
            new PlaceFormatSpec(PlaceStyle::CityCountry),
            new IsoCountryMap('de_DE')
        );

        self::assertSame('Hamburg, Deutschland', $processor->getBirthPlaceShort());
    }

    /**
     * The level count and direction belong to PlaceStyle::Levels; a city style
     * must ignore them entirely.
     *
     * @return void
     */
    #[Test]
    public function cityStylesIgnoreLevelSettings(): void
    {
        $individual = self::createStub(Individual::class);
        $individual->method('getBirthPlace')->willReturn(
            $this->placeStub('Hamburg, Wandsbek, Deutschland', ['Hamburg', 'Wandsbek', 'Deutschland'])
        );

        $processor = new PlaceProcessor(
            $individual,
            new PlaceFormatSpec(PlaceStyle::CityCountry, 3, true),
            new IsoCountryMap('en_US')
        );

        self::assertSame('Hamburg, Deutschland', $processor->getBirthPlaceShort());
    }
}
