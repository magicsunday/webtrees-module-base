<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use Fisharebest\Webtrees\Family;
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
use function explode;

/**
 * Locks the {@see PlaceProcessor} contract: PlaceStyle::Full/Levels/CityCountry
 * shortening (including the from-end direction and the alpha-3 country-code
 * spell-out CityCountry performs through {@see IsoCountryMap}), the full
 * (unshortened) birth/death/marriage accessors, and the first-spouse-family
 * wiring of getMarriagePlace().
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(PlaceProcessor::class)]
#[UsesClass(PlaceFormatSpec::class)]
#[UsesClass(PlaceStyle::class)]
#[UsesClass(IsoCountryMap::class)]
final class PlaceProcessorTest extends TestCase
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
     * Symmetric to setUp(): the last test in this class would otherwise leave a
     * locale-primed map behind in the process-wide static, leaking into
     * whichever test class runs next — {@see IsoCountryMapTest::tearDown()}
     * guards the same statics for the same reason.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        IsoCountryMap::clearCache();

        parent::tearDown();
    }

    /**
     * Builds a Place stub whose firstParts()/lastParts() actually honour the count
     * argument by slicing the real part list. This locks BOTH the $this->format->levels
     * value the processor passes to the seam and the first-vs-last selection — a stub
     * that returned a fixed pre-truncated collection would green even if the processor
     * passed a wrong count.
     *
     * @param string        $gedcomName Full GEDCOM place name returned by gedcomName()
     * @param array<string> $parts      Ordered place-name segments, most specific (locality) first
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
     * The Full style returns the untouched recorded name. The gedcomName
     * deliberately lacks the spaces `implode(', ', $parts)` would add, so an
     * implementation that reassembles the parts instead of returning the
     * recorded name fails this test.
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

        $place = $this->placeStub('Mitte,Berlin,Germany', ['Mitte', 'Berlin', 'Germany']);

        self::assertSame('Mitte,Berlin,Germany', $processor->shortPlaceName($place));
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
            new PlaceFormatSpec(PlaceStyle::Levels, 2),
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
     * @param int    $levels   Number of hierarchy levels the format spec keeps
     * @param string $expected Expected shortened place name
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
     * @param string        $gedcomName Full GEDCOM place name returned by gedcomName()
     * @param array<string> $parts      Ordered place-name segments, most specific (locality) first
     * @param string        $expected   Expected CityCountry-shortened place name
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
                'Berlin (Stadt)',
                ['Berlin'],
                'Berlin',
            ],
            'an alpha-3 code is spelled out' => [
                'Hamburg, DEU',
                ['Hamburg', 'DEU'],
                'Hamburg, Germany',
            ],
            'a Chapman home-nation code folds onto the country' => [
                'London, ENG',
                ['London', 'ENG'],
                'London, United Kingdom',
            ],
            'a two-letter segment is never expanded' => [
                'Dover,DE',
                ['Dover', 'DE'],
                'Dover, DE',
            ],
            'a German state abbreviation survives' => [
                'Ulm,BW',
                ['Ulm', 'BW'],
                'Ulm, BW',
            ],
            'an unresolvable three-letter token stays put' => [
                'Ulm,XYZ',
                ['Ulm', 'XYZ'],
                'Ulm, XYZ',
            ],
            'a plain country name is not translated' => [
                'Hamburg,Deutschland',
                ['Hamburg', 'Deutschland'],
                'Hamburg, Deutschland',
            ],
            'a longer unresolvable segment stays put' => [
                'London,Middlesex',
                ['London', 'Middlesex'],
                'London, Middlesex',
            ],
            'a non-empty name with no parts pins the outerSegments() type guard' => [
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

    /**
     * The two ISO city styles keep the locality and resolve the country segment
     * to its ISO-3166-1 code — alpha-2 for CityIso2, alpha-3 for CityIso3. A
     * multi-segment place resolves its last segment; a lone segment is treated
     * as the country itself (GVExport parity) unless it is an ambiguous
     * city/country name, in which case the plain text survives. Every failure
     * path degrades to the recorded text rather than dropping the place.
     *
     * @param PlaceStyle    $style      City style under test
     * @param string        $gedcomName Full GEDCOM place name returned by gedcomName()
     * @param array<string> $parts      Ordered place-name segments, most specific (locality) first
     * @param string        $expected   Expected shortened place name
     *
     * @return void
     */
    #[Test]
    #[DataProvider('cityIsoProvider')]
    public function cityIsoStylesResolveTheCountrySegment(
        PlaceStyle $style,
        string $gedcomName,
        array $parts,
        string $expected,
    ): void {
        $individual = self::createStub(Individual::class);
        $individual->method('getBirthPlace')->willReturn($this->placeStub($gedcomName, $parts));

        $processor = new PlaceProcessor(
            $individual,
            new PlaceFormatSpec($style),
            new IsoCountryMap('en_US')
        );

        self::assertSame($expected, $processor->getBirthPlaceShort());
    }

    /**
     * @return array<string, array{0: PlaceStyle, 1: string, 2: array<string>, 3: string}>
     */
    public static function cityIsoProvider(): array
    {
        return [
            // CityIso2
            'iso2 collapses a four-segment place to city + alpha-2' => [
                PlaceStyle::CityIso2,
                'Hamburg, Wandsbek, Schleswig-Holstein, Deutschland',
                ['Hamburg', 'Wandsbek', 'Schleswig-Holstein', 'Deutschland'],
                'Hamburg, DE',
            ],
            'iso2 resolves a two-segment place to city + alpha-2' => [
                PlaceStyle::CityIso2,
                'Hamburg, Deutschland',
                ['Hamburg', 'Deutschland'],
                'Hamburg, DE',
            ],
            'iso2 keeps a lone unresolvable segment whole' => [
                PlaceStyle::CityIso2,
                'Berlin',
                ['Berlin'],
                'Berlin',
            ],
            'iso2 treats a lone country name as the country' => [
                PlaceStyle::CityIso2,
                'Deutschland',
                ['Deutschland'],
                'DE',
            ],
            'iso2 keeps a lone ambiguous city/country name (Luxembourg)' => [
                PlaceStyle::CityIso2,
                'Luxembourg',
                ['Luxembourg'],
                'Luxembourg',
            ],
            'iso2 keeps a lone ambiguous city/country name (Monaco)' => [
                PlaceStyle::CityIso2,
                'Monaco',
                ['Monaco'],
                'Monaco',
            ],
            'iso2 keeps an unresolvable last segment as-is' => [
                PlaceStyle::CityIso2,
                'London, Middlesex',
                ['London', 'Middlesex'],
                'London, Middlesex',
            ],
            'iso2 resolves an alias last segment (England -> GB)' => [
                PlaceStyle::CityIso2,
                'London, England',
                ['London', 'England'],
                'London, GB',
            ],
            'iso2 resolves an alpha-3 last segment back to alpha-2' => [
                PlaceStyle::CityIso2,
                'Hamburg, DEU',
                ['Hamburg', 'DEU'],
                'Hamburg, DE',
            ],
            'iso2 keeps a two-letter last segment verbatim even when it aliases a country (York, UK)' => [
                PlaceStyle::CityIso2,
                'York, UK',
                ['York', 'UK'],
                'York, UK',
            ],
            'iso2 keeps a lone two-letter segment verbatim even when it aliases a country (UK)' => [
                PlaceStyle::CityIso2,
                'UK',
                ['UK'],
                'UK',
            ],

            // CityIso3
            'iso3 collapses a four-segment place to city + alpha-3' => [
                PlaceStyle::CityIso3,
                'Hamburg, Wandsbek, Schleswig-Holstein, Deutschland',
                ['Hamburg', 'Wandsbek', 'Schleswig-Holstein', 'Deutschland'],
                'Hamburg, DEU',
            ],
            'iso3 resolves a two-segment place to city + alpha-3' => [
                PlaceStyle::CityIso3,
                'Hamburg, Deutschland',
                ['Hamburg', 'Deutschland'],
                'Hamburg, DEU',
            ],
            'iso3 keeps a lone unresolvable segment whole' => [
                PlaceStyle::CityIso3,
                'Berlin',
                ['Berlin'],
                'Berlin',
            ],
            'iso3 treats a lone country name as the country' => [
                PlaceStyle::CityIso3,
                'Deutschland',
                ['Deutschland'],
                'DEU',
            ],
            'iso3 keeps a lone ambiguous city/country name (Luxembourg)' => [
                PlaceStyle::CityIso3,
                'Luxembourg',
                ['Luxembourg'],
                'Luxembourg',
            ],
            'iso3 keeps an unresolvable last segment as-is' => [
                PlaceStyle::CityIso3,
                'London, Middlesex',
                ['London', 'Middlesex'],
                'London, Middlesex',
            ],
            'iso3 keeps a two-letter last segment verbatim (Dover, DE = Delaware)' => [
                PlaceStyle::CityIso3,
                'Dover, DE',
                ['Dover', 'DE'],
                'Dover, DE',
            ],
            'iso3 keeps a two-letter last segment verbatim (Ulm, BW = Baden-Württemberg)' => [
                PlaceStyle::CityIso3,
                'Ulm, BW',
                ['Ulm', 'BW'],
                'Ulm, BW',
            ],
            'iso3 keeps a two-letter last segment verbatim (Springfield, IL = Illinois)' => [
                PlaceStyle::CityIso3,
                'Springfield, IL',
                ['Springfield', 'IL'],
                'Springfield, IL',
            ],
            'iso3 keeps a lone two-letter segment verbatim' => [
                PlaceStyle::CityIso3,
                'DE',
                ['DE'],
                'DE',
            ],
        ];
    }

    /**
     * getMarriagePlace() has a branch no other accessor shares: it reads the
     * individual's FIRST spouse family rather than a direct place accessor, and
     * returns an empty string when there is none. Both outcomes went through
     * the constructor rewrite untested.
     *
     * The two-spouse-family row stubs a second family with a different
     * recorded place: only reading the first family's place, and ignoring the
     * second, proves the accessor actually keys off Collection::first() rather
     * than e.g. last() — a single-family fixture cannot distinguish the two.
     *
     * The spec deliberately uses PlaceStyle::Levels, 1 rather than
     * PlaceStyle::Full: under Full, fullPlaceName() and shortPlaceName() return
     * the same string by construction, so a regression that routed
     * getMarriagePlace() through the shortening path would stay green. With
     * Levels(1) the two paths diverge — the full accessor must still return the
     * whole recorded name ('Hamburg, Germany') while the shortened path would
     * truncate to the first segment ('Hamburg').
     *
     * @param array<string> $familyPlaceNames Recorded marriage place per stubbed spouse family, in order; empty for no family
     * @param string        $expected         Expected return value of getMarriagePlace()
     *
     * @return void
     */
    #[Test]
    #[DataProvider('marriagePlaceProvider')]
    public function getMarriagePlaceReflectsTheFirstSpouseFamily(array $familyPlaceNames, string $expected): void
    {
        $individual = self::createStub(Individual::class);
        $families   = [];

        foreach ($familyPlaceNames as $familyPlaceName) {
            $family = self::createStub(Family::class);
            $family->method('getMarriagePlace')->willReturn(
                $this->placeStub($familyPlaceName, explode(', ', $familyPlaceName))
            );

            $families[] = $family;
        }

        $individual->method('spouseFamilies')->willReturn(new Collection($families));

        $processor = new PlaceProcessor(
            $individual,
            new PlaceFormatSpec(PlaceStyle::Levels, 1),
            new IsoCountryMap('en_US')
        );

        self::assertSame($expected, $processor->getMarriagePlace());
    }

    /**
     * @return array<string, array{0: array<string>, 1: string}>
     */
    public static function marriagePlaceProvider(): array
    {
        return [
            'a second spouse family is ignored, the first wins' => [
                ['Hamburg, Germany', 'Munich, Germany'], 'Hamburg, Germany',
            ],
            'no spouse family yields an empty string' => [[], ''],
        ];
    }

    /**
     * getDeathPlaceShort() went through the same constructor rewrite as
     * getBirthPlaceShort(), but every other shortening test reads the birth
     * place. Without this, a wiring mix-up that pointed it at the birth place
     * instead of the death place would go uncaught.
     *
     * @return void
     */
    #[Test]
    public function getDeathPlaceShortShortensTheDeathPlace(): void
    {
        $individual = self::createStub(Individual::class);
        $individual->method('getDeathPlace')->willReturn(
            $this->placeStub('Mitte, Berlin, Germany', ['Mitte', 'Berlin', 'Germany'])
        );

        $processor = new PlaceProcessor(
            $individual,
            new PlaceFormatSpec(PlaceStyle::Levels, 2),
            new IsoCountryMap('en_US')
        );

        self::assertSame('Mitte, Berlin', $processor->getDeathPlaceShort());
    }

    /**
     * getBirthPlace() and getDeathPlace() read the individual's birth/death
     * place accessor directly and return the whole recorded GEDCOM place name,
     * unshortened. Both went untested despite the same wiring risk that
     * justifies {@see self::getDeathPlaceShortShortensTheDeathPlace()}: a
     * mix-up pointing the death accessor at the birth place (or vice versa)
     * would go unnoticed without this.
     *
     * PlaceStyle::Levels, 1 is used deliberately, not PlaceStyle::Full: under
     * Full, the full and short accessors return the same string by
     * construction, so a regression that routed a full accessor through the
     * shortening path would stay green. The gedcomName also lacks the spaces
     * the joined parts would add, so a reassembling implementation fails this
     * test too.
     *
     * @param string                          $individualMethod    Individual accessor stubbed with the place ('getBirthPlace' or 'getDeathPlace')
     * @param callable(PlaceProcessor):string $callProcessorMethod Invokes the PlaceProcessor accessor under test
     *
     * @return void
     */
    #[Test]
    #[DataProvider('fullPlaceAccessorProvider')]
    public function fullAccessorsReturnTheWholeRecordedNameUnshortened(
        string $individualMethod,
        callable $callProcessorMethod,
    ): void {
        $individual = self::createStub(Individual::class);
        $individual->method($individualMethod)->willReturn(
            $this->placeStub('Mitte,Berlin,Germany', ['Mitte', 'Berlin', 'Germany'])
        );

        $processor = new PlaceProcessor(
            $individual,
            new PlaceFormatSpec(PlaceStyle::Levels, 1),
            new IsoCountryMap('en_US')
        );

        self::assertSame('Mitte,Berlin,Germany', $callProcessorMethod($processor));
    }

    /**
     * @return array<string, array{0: string, 1: callable(PlaceProcessor):string}>
     */
    public static function fullPlaceAccessorProvider(): array
    {
        return [
            'getBirthPlace reads the birth place unshortened' => [
                'getBirthPlace',
                static fn (PlaceProcessor $processor): string => $processor->getBirthPlace(),
            ],
            'getDeathPlace reads the death place unshortened' => [
                'getDeathPlace',
                static fn (PlaceProcessor $processor): string => $processor->getDeathPlace(),
            ],
        ];
    }
}
