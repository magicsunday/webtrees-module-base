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
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Factories\CacheFactory;
use Fisharebest\Webtrees\Factories\CalendarDateFactory;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use MagicSunday\Webtrees\ModuleBase\Model\FactView;
use MagicSunday\Webtrees\ModuleBase\Model\PlaceFormatSpec;
use MagicSunday\Webtrees\ModuleBase\Model\PlaceStyle;
use MagicSunday\Webtrees\ModuleBase\Processor\DateProcessor;
use MagicSunday\Webtrees\ModuleBase\Processor\FactResolver;
use MagicSunday\Webtrees\ModuleBase\Processor\PlaceProcessor;
use MagicSunday\Webtrees\ModuleBase\Support\Locale\IsoCountryMap;
use MagicSunday\Webtrees\ModuleBase\Support\PlainText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * Locks the {@see FactResolver} contract: which tags a chart box reserves rows for, which fact
 * each row shows and how its date, place and value are rendered.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(FactResolver::class)]
#[UsesClass(FactView::class)]
#[UsesClass(DateProcessor::class)]
#[UsesClass(PlaceProcessor::class)]
#[UsesClass(PlaceFormatSpec::class)]
#[UsesClass(PlaceStyle::class)]
#[UsesClass(IsoCountryMap::class)]
#[UsesClass(PlainText::class)]
final class FactResolverTest extends TestCase
{
    /**
     * The webtrees services the dates need.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Registry::cache(new CacheFactory());
        Registry::calendarDateFactory(new CalendarDateFactory());
        I18N::init('en-US', true);
        IsoCountryMap::clearCache();
    }

    /**
     * The country map memoises into process-wide statics, so the last test must not leave them primed.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        IsoCountryMap::clearCache();

        parent::tearDown();
    }

    /**
     * Builds a resolver around a tree that reports the given CHART_BOX_TAGS preference.
     *
     * @param string $chartBoxTags      The value of the tree preference
     * @param string $compactDateFormat The compact date format the resolver is configured with
     *
     * @return FactResolver
     */
    private function resolverWithTags(string $chartBoxTags, string $compactDateFormat = '%d.%m.%Y'): FactResolver
    {
        $tree = self::createStub(Tree::class);
        $tree->method('getPreference')->willReturnCallback(
            static fn (string $name): string => $name === 'CHART_BOX_TAGS' ? $chartBoxTags : ''
        );

        return new FactResolver(
            $tree,
            new PlaceFormatSpec(PlaceStyle::Full),
            new IsoCountryMap('en_US'),
            $compactDateFormat,
        );
    }

    /**
     * Builds a fact the way webtrees hands it to a chart: with a label, a date, a place and a value.
     *
     * @param string $label The localised label
     * @param string $date  The GEDCOM date
     * @param string $place The full GEDCOM place name
     * @param string $value The canonical fact value
     *
     * @return Fact
     */
    private function factStub(string $label, string $date, string $place, string $value = ''): Fact
    {
        $placeStub = self::createStub(Place::class);
        $placeStub->method('gedcomName')->willReturn($place);

        $fact = self::createStub(Fact::class);
        $fact->method('label')->willReturn($label);
        $fact->method('date')->willReturn(new Date($date));
        $fact->method('place')->willReturn($placeStub);
        $fact->method('value')->willReturn($value);

        return $fact;
    }

    /**
     * Builds an individual whose facts() answers per requested tag, like the webtrees record does
     * after it has applied the privacy of the viewer. The privacy rests on the default access level
     * of facts() and the first fact is the first one in record order, so the double fails when a caller
     * passes an access level of its own or asks for the facts sorted.
     *
     * @param array<string, Fact|list<Fact>> $factsByTag The visible facts per tag in record order, a missing
     *                                                   tag has no visible fact
     *
     * @return Individual
     */
    private function individualWith(array $factsByTag): Individual
    {
        $individual = self::createStub(Individual::class);
        $individual->method('facts')->willReturnCallback(
            static function (array $filter = [], ...$rest) use ($factsByTag): Collection {
                self::assertFalse($rest[0] ?? false, 'facts() must keep the record order');
                self::assertNull($rest[1] ?? null, 'facts() must keep the access level of the viewer');

                $found = [];

                foreach ($filter as $tag) {
                    if (!is_string($tag) || !isset($factsByTag[$tag])) {
                        continue;
                    }

                    $entry = $factsByTag[$tag];

                    foreach (is_array($entry) ? $entry : [$entry] as $fact) {
                        $found[] = $fact;
                    }
                }

                return new Collection($found);
            }
        );

        return $individual;
    }

    /**
     * @return array<string, array{string, list<string>, list<string>}>
     */
    public static function optionalTagsProvider(): array
    {
        // [ CHART_BOX_TAGS value, caller exclusions, expected optional tags ]
        return [
            'comma separated list'                 => ['OCCU,RESI,MARR', [], ['OCCU', 'RESI', 'MARR']],
            'whitespace and mixed separators'      => [" OCCU , RESI\nMARR ", [], ['OCCU', 'RESI', 'MARR']],
            'lower case is normalised'             => ['occu,Resi', [], ['OCCU', 'RESI']],
            'custom underscore tags are kept'      => ['_MILT,OCCU', [], ['_MILT', 'OCCU']],
            'duplicates keep the first occurrence' => ['OCCU,RESI,OCCU', [], ['OCCU', 'RESI']],
            'birth equivalents are dropped'        => ['BIRT,CHR,BAPM,OCCU', [], ['OCCU']],
            'death equivalents are dropped'        => ['DEAT,BURI,CREM,OCCU', [], ['OCCU']],
            'caller exclusions are dropped'        => ['OCCU,RESI,MARR', ['MARR'], ['OCCU', 'RESI']],
            'caller exclusions ignore the case'    => ['OCCU,MARR', ['marr'], ['OCCU']],
            'an empty preference gives no tag'     => ['', [], []],
        ];
    }

    /**
     * The optional tags come from the tree preference, without the events that always have their own row.
     *
     * @param string       $preference The value of the tree preference
     * @param list<string> $excluded   The tags the caller excludes
     * @param list<string> $expected   The expected optional tags
     *
     * @return void
     */
    #[Test]
    #[DataProvider('optionalTagsProvider')]
    public function optionalTagsFollowTheTreePreference(string $preference, array $excluded, array $expected): void
    {
        self::assertSame($expected, $this->resolverWithTags($preference)->optionalTags($excluded));
    }

    /**
     * Birth and death always come first, the optional tags follow when the chart shows them.
     *
     * @return void
     */
    #[Test]
    public function effectiveTagsPutBirthAndDeathFirstThenTheOptionalTags(): void
    {
        $resolver = $this->resolverWithTags('OCCU,RESI');

        self::assertSame(['BIRT', 'DEAT', 'OCCU', 'RESI'], $resolver->effectiveTags(true));
        self::assertSame(['BIRT', 'DEAT', 'OCCU'], $resolver->effectiveTags(true, ['RESI']));
    }

    /**
     * Without the additional facts only the birth and the death row remain.
     *
     * @return void
     */
    #[Test]
    public function effectiveTagsOmitTheOptionalTagsWhenTheChartHidesThem(): void
    {
        self::assertSame(['BIRT', 'DEAT'], $this->resolverWithTags('OCCU,RESI')->effectiveTags(false));
    }

    /**
     * A person without any visible fact still yields one null slot per row, so rows stay aligned.
     *
     * @return void
     */
    #[Test]
    public function factsForAlignsTheSlotsWithTheEffectiveTagsAndLeavesMissingFactsNull(): void
    {
        $resolver   = $this->resolverWithTags('OCCU,RESI');
        $individual = $this->individualWith([]);

        self::assertSame([null, null, null, null], $resolver->factsFor($individual, true));
        self::assertSame([null, null], $resolver->factsFor($individual, false));
    }

    /**
     * Each slot carries the label, date, place, full place and value of its fact.
     *
     * @return void
     */
    #[Test]
    public function factsForRendersTheTextsOfEachFact(): void
    {
        $resolver   = $this->resolverWithTags('OCCU');
        $individual = $this->individualWith([
            'BIRT' => $this->factStub('Birth', '12 MAY 1832', 'Berlin, Germany'),
            'DEAT' => $this->factStub('Death', 'ABT 1890', 'Paris, France'),
            'OCCU' => $this->factStub('Occupation', '', '', 'Smith <Ltd> &amp; Co'),
        ]);

        $facts = $resolver->factsFor($individual, true);

        self::assertEquals(
            [
                new FactView(
                    tag: 'BIRT',
                    label: 'Birth',
                    date: '12.05.1832',
                    place: 'Berlin, Germany',
                    placeFull: 'Berlin, Germany',
                    value: '',
                ),
                new FactView(
                    tag: 'DEAT',
                    label: 'Death',
                    date: 'about 1890',
                    place: 'Paris, France',
                    placeFull: 'Paris, France',
                    value: '',
                ),
                new FactView(
                    tag: 'OCCU',
                    label: 'Occupation',
                    date: '',
                    place: '',
                    placeFull: '',
                    value: 'Smith <Ltd> &amp; Co',
                ),
            ],
            $facts
        );
    }

    /**
     * The birth slot takes the first existing birth equivalent and reports its concrete tag.
     *
     * @return void
     */
    #[Test]
    public function factsForUsesTheFirstExistingBirthAndDeathEquivalent(): void
    {
        $resolver   = $this->resolverWithTags('');
        $individual = $this->individualWith([
            'CHR'  => $this->factStub('Christening', '1 JAN 1900', ''),
            'BAPM' => $this->factStub('Baptism', '2 JAN 1900', ''),
            'BURI' => $this->factStub('Burial', '3 FEB 1970', ''),
            'CREM' => $this->factStub('Cremation', '4 FEB 1970', ''),
        ]);

        [$birth, $death] = $resolver->factsFor($individual, false);

        self::assertInstanceOf(FactView::class, $birth);
        self::assertSame('CHR', $birth->tag);
        self::assertSame('01.01.1900', $birth->date);

        self::assertInstanceOf(FactView::class, $death);
        self::assertSame('BURI', $death->tag);
        self::assertSame('03.02.1970', $death->date);
    }

    /**
     * A birth equivalent listed in the tree preference never shows up as an optional fact, because the
     * birth slot already carries the birth events.
     *
     * @return void
     */
    #[Test]
    public function factsForDoesNotRepeatBirthEquivalentsAsOptionalFacts(): void
    {
        $resolver   = $this->resolverWithTags('CHR,OCCU');
        $individual = $this->individualWith([
            'CHR'  => $this->factStub('Christening', '1 JAN 1900', ''),
            'OCCU' => $this->factStub('Occupation', '', '', 'Smith'),
        ]);

        $facts = $resolver->factsFor($individual, true);

        self::assertCount(3, $facts, 'birth slot, death slot and the one optional slot of OCCU');
        self::assertSame('CHR', $facts[0]?->tag);
        self::assertNull($facts[1]);
        self::assertSame('OCCU', $facts[2]?->tag);
    }

    /**
     * A fact that is absent from the record's answer leaves its slot empty while a visible optional
     * fact of the same person still shows. The record applies the privacy of the viewer before it
     * answers, so a hidden fact reaches the resolver as an absent one.
     *
     * @return void
     */
    #[Test]
    public function factsForKeepsTheSlotOfAHiddenFactEmpty(): void
    {
        $resolver   = $this->resolverWithTags('OCCU');
        $individual = $this->individualWith([
            'OCCU' => $this->factStub('Occupation', '', '', 'Smith'),
        ]);

        $facts = $resolver->factsFor($individual, true);

        self::assertNull($facts[0]);
        self::assertNull($facts[1]);
        self::assertSame('Smith', $facts[2]?->value);
    }

    /**
     * A tag the caller excludes gets no slot, so the rows of a person agree with the effective tags
     * the chart reserves for the same exclusion list.
     *
     * @return void
     */
    #[Test]
    public function factsForLeavesOutTheTagsTheCallerExcludes(): void
    {
        $resolver   = $this->resolverWithTags('OCCU,RESI');
        $individual = $this->individualWith([
            'OCCU' => $this->factStub('Occupation', '', '', 'Smith'),
            'RESI' => $this->factStub('Residence', '', 'Berlin'),
        ]);

        $facts = $resolver->factsFor($individual, true, ['OCCU']);

        self::assertCount(count($resolver->effectiveTags(true, ['OCCU'])), $facts, 'rows agree with the effective tags');
        self::assertCount(3, $facts, 'birth slot, death slot and the one remaining optional slot');
        self::assertSame('RESI', $facts[2]?->tag);
    }

    /**
     * When a person has both a birth and a christening, or a death and a burial, the birth and the
     * death win, because they come first in the group.
     *
     * @return void
     */
    #[Test]
    public function factsForPrefersBirthAndDeathOverTheirEquivalents(): void
    {
        $resolver   = $this->resolverWithTags('');
        $individual = $this->individualWith([
            'BIRT' => $this->factStub('Birth', '1 JAN 1900', ''),
            'CHR'  => $this->factStub('Christening', '2 JAN 1900', ''),
            'DEAT' => $this->factStub('Death', '3 FEB 1970', ''),
            'BURI' => $this->factStub('Burial', '4 FEB 1970', ''),
        ]);

        [$birth, $death] = $resolver->factsFor($individual, false);

        self::assertSame('BIRT', $birth?->tag);
        self::assertSame('DEAT', $death?->tag);
    }

    /**
     * When a person has several facts of one tag, the first one in record order is shown.
     *
     * @return void
     */
    #[Test]
    public function factsForShowsTheFirstFactOfATagInRecordOrder(): void
    {
        $resolver   = $this->resolverWithTags('OCCU');
        $individual = $this->individualWith([
            'OCCU' => [
                $this->factStub('Occupation', '', '', 'Smith'),
                $this->factStub('Occupation', '', '', 'Baker'),
            ],
        ]);

        self::assertSame('Smith', $resolver->factsFor($individual, true)[2]?->value);
    }

    /**
     * A baptism alone fills the birth slot and a cremation alone fills the death slot.
     *
     * @return void
     */
    #[Test]
    public function factsForFallsBackToBaptismAndCremation(): void
    {
        $resolver   = $this->resolverWithTags('');
        $individual = $this->individualWith([
            'BAPM' => $this->factStub('Baptism', '2 JAN 1900', ''),
            'CREM' => $this->factStub('Cremation', '4 FEB 1970', ''),
        ]);

        [$birth, $death] = $resolver->factsFor($individual, false);

        self::assertSame('BAPM', $birth?->tag);
        self::assertSame('CREM', $death?->tag);
    }

    /**
     * The label of a custom fact type is escaped by webtrees, so it is reduced to plain text.
     *
     * @return void
     */
    #[Test]
    public function factsForReducesTheEscapedLabelOfACustomFactToPlainText(): void
    {
        $resolver   = $this->resolverWithTags('FACT');
        $individual = $this->individualWith([
            'FACT' => $this->factStub('Research &amp; development', '', '', 'Lab'),
        ]);

        self::assertSame('Research & development', $resolver->factsFor($individual, true)[2]?->label);
    }

    /**
     * A complete date follows the compact format the resolver is configured with.
     *
     * @return void
     */
    #[Test]
    public function factsForFormatsTheDateWithTheConfiguredCompactFormat(): void
    {
        $resolver   = $this->resolverWithTags('', '%Y/%m/%d');
        $individual = $this->individualWith([
            'BIRT' => $this->factStub('Birth', '12 MAY 1832', ''),
        ]);

        self::assertSame('1832/05/12', $resolver->factsFor($individual, false)[0]?->date);
    }

    /**
     * A custom tag of the preference, which starts with an underscore, gets the fact of that tag.
     *
     * @return void
     */
    #[Test]
    public function factsForResolvesTheFactOfACustomTag(): void
    {
        $resolver   = $this->resolverWithTags('_MILT');
        $individual = $this->individualWith([
            '_MILT' => $this->factStub('Military service', '', '', 'Infantry'),
        ]);

        $facts = $resolver->factsFor($individual, true);

        self::assertSame('_MILT', $facts[2]?->tag);
        self::assertSame('Infantry', $facts[2]->value);
    }

    /**
     * The place is shortened by the configured style, the full name stays available for the tooltip.
     *
     * @return void
     */
    #[Test]
    public function factsForShortensThePlaceByTheConfiguredStyle(): void
    {
        $tree = self::createStub(Tree::class);
        $tree->method('getPreference')->willReturn('');

        $resolver = new FactResolver(
            $tree,
            new PlaceFormatSpec(PlaceStyle::Levels, 1),
            new IsoCountryMap('en_US'),
            '%d.%m.%Y',
        );

        $placeStub = self::createStub(Place::class);
        $placeStub->method('gedcomName')->willReturn('Charlottenburg, Berlin, Germany');
        $placeStub->method('firstParts')->willReturn(new Collection(['Charlottenburg']));

        $fact = self::createStub(Fact::class);
        $fact->method('label')->willReturn('Birth');
        $fact->method('date')->willReturn(new Date(''));
        $fact->method('place')->willReturn($placeStub);
        $fact->method('value')->willReturn('');

        $birth = $resolver->factsFor($this->individualWith(['BIRT' => $fact]), false)[0];

        self::assertInstanceOf(FactView::class, $birth);
        self::assertSame('Charlottenburg', $birth->place);
        self::assertSame('Charlottenburg, Berlin, Germany', $birth->placeFull);
    }
}
