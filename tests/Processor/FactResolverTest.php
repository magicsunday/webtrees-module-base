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
use Fisharebest\Webtrees\Factories\CalendarDateFactory;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\GedcomRecord;
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
use MagicSunday\Webtrees\ModuleBase\Test\Double\InMemoryCacheFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

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

        Registry::cache(new InMemoryCacheFactory());
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
     * @param string            $label  The localised label
     * @param string            $date   The GEDCOM date
     * @param string            $place  The full GEDCOM place name
     * @param string            $value  The canonical fact value
     * @param GedcomRecord|null $target The record the fact links to, null for a fact without a link
     *
     * @return Fact
     */
    private function factStub(string $label, string $date, string $place, string $value = '', ?GedcomRecord $target = null): Fact
    {
        $placeStub = self::createStub(Place::class);
        $placeStub->method('gedcomName')->willReturn($place);

        $fact = self::createStub(Fact::class);
        $fact->method('label')->willReturn($label);
        $fact->method('date')->willReturn(new Date($date));
        $fact->method('place')->willReturn($placeStub);
        $fact->method('value')->willReturn($value);
        $fact->method('target')->willReturn($target);

        return $fact;
    }

    /**
     * Builds an individual whose facts() answers per requested tag, like the webtrees record does
     * after it has applied the privacy of the viewer. The privacy rests on the default access level
     * of facts() and the first fact is the first one in record order, so the double fails when a caller
     * passes an access level of its own or asks for the facts sorted.
     *
     * @param array<string, Fact|list<Fact>> $factsByTag     The visible facts per tag in record order, a missing
     *                                                       tag has no visible fact
     * @param list<Family>                   $spouseFamilies The families of the individual as spouse that the
     *                                                       viewer may see
     *
     * @return Individual
     */
    private function individualWith(array $factsByTag, array $spouseFamilies = []): Individual
    {
        $individual = self::createStub(Individual::class);
        $individual->method('spouseFamilies')->willReturn(new Collection($spouseFamilies));
        $individual->method('facts')->willReturnCallback(
            static function (array $filter = [], ...$rest) use ($factsByTag): Collection {
                self::assertFalse($rest[0] ?? false, 'facts() must keep the record order');
                self::assertNull($rest[1] ?? null, 'facts() must keep the access level of the viewer');
                self::assertFalse($rest[2] ?? false, 'facts() must keep the deleted facts of the record');

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
     * Builds a family of the individual with the given visible facts and spouse. The family hands out its
     * facts under the same conditions as the individual, so the double fails when a caller changes them.
     *
     * @param array<string, Fact> $factsByTag The visible facts of the family per tag
     * @param string|null         $spouseName The name markup of the other spouse, null for a family without one
     *
     * @return Family
     */
    private function familyWith(array $factsByTag, ?string $spouseName): Family
    {
        $family = self::createStub(Family::class);
        $family->method('facts')->willReturnCallback(
            static function (array $filter = [], ...$rest) use ($factsByTag): Collection {
                self::assertFalse($rest[0] ?? false, 'facts() must keep the record order');
                self::assertNull($rest[1] ?? null, 'facts() must keep the access level of the viewer');

                $found = [];

                foreach ($filter as $tag) {
                    if (is_string($tag) && isset($factsByTag[$tag])) {
                        $found[] = $factsByTag[$tag];
                    }
                }

                return new Collection($found);
            }
        );

        $spouse = null;

        if ($spouseName !== null) {
            $spouse = self::createStub(Individual::class);
            $spouse->method('fullName')->willReturn($spouseName);
        }

        $family->method('spouse')->willReturn($spouse);

        return $family;
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function optionalTagsProvider(): array
    {
        // [ CHART_BOX_TAGS value, expected optional tags ]
        return [
            'comma separated list'                 => ['OCCU,RESI,EDUC', ['OCCU', 'RESI', 'EDUC']],
            'whitespace and mixed separators'      => [" OCCU , RESI\nEDUC ", ['OCCU', 'RESI', 'EDUC']],
            'lower case is normalised'             => ['occu,Resi', ['OCCU', 'RESI']],
            'custom underscore tags are kept'      => ['_MILT,OCCU', ['_MILT', 'OCCU']],
            'duplicates keep the first occurrence' => ['OCCU,RESI,OCCU', ['OCCU', 'RESI']],
            'birth equivalents are dropped'        => ['BIRT,CHR,BAPM,OCCU', ['OCCU']],
            'death equivalents are dropped'        => ['DEAT,BURI,CREM,OCCU', ['OCCU']],
            'an empty preference gives no tag'     => ['', []],
        ];
    }

    /**
     * The optional tags come from the tree preference, without the events that always have their own row.
     *
     * @param string       $preference The value of the tree preference
     * @param list<string> $expected   The expected optional tags
     *
     * @return void
     */
    #[Test]
    #[DataProvider('optionalTagsProvider')]
    public function optionalTagsFollowTheTreePreference(string $preference, array $expected): void
    {
        self::assertSame($expected, $this->resolverWithTags($preference)->optionalTags());
    }

    /**
     * A person without any visible fact still yields one null slot per row, so rows stay aligned.
     *
     * @return void
     */
    #[Test]
    public function factsForKeepsOneNullSlotPerRowForAPersonWithoutFacts(): void
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
                    sortDay: 2390316,
                ),
                new FactView(
                    tag: 'DEAT',
                    label: 'Death',
                    date: 'about 1890',
                    place: 'Paris, France',
                    placeFull: 'Paris, France',
                    value: '',
                    sortDay: 2411369,
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
     * A fact that is absent from the record's answer leaves only its own slot empty while a visible
     * optional fact of the same person still shows. The privacy of the viewer is pinned by the access
     * level the double asserts, since the record answers without the facts it hides.
     *
     * @return void
     */
    #[Test]
    public function factsForKeepsTheSlotOfAnAbsentFactEmpty(): void
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
     * A fact such as a shared note stores a pointer to a record as its value. The pointer is no text for a
     * reader, so the fact shows the plain-text name of the record it links to.
     *
     * @return void
     */
    #[Test]
    public function factsForShowsTheNameOfTheRecordAPointerValueLinksTo(): void
    {
        $note = self::createStub(GedcomRecord::class);
        $note->method('fullName')->willReturn('<span class="NAME">Shared &amp; note</span>');
        $note->method('canShowName')->willReturn(true);

        $resolver   = $this->resolverWithTags('SNOTE');
        $individual = $this->individualWith([
            'SNOTE' => $this->factStub('Shared note', '', '', '@N3@', $note),
        ]);

        $facts = $resolver->factsFor($individual, true);

        self::assertSame('SNOTE', $facts[2]?->tag);
        self::assertSame('Shared & note', $facts[2]->value);
    }

    /**
     * A pointer to a record that the viewer cannot get gives no value, so the raw pointer never reaches
     * the chart.
     *
     * @return void
     */
    #[Test]
    public function factsForGivesNoValueForAPointerWithoutAVisibleRecord(): void
    {
        $resolver   = $this->resolverWithTags('SNOTE');
        $individual = $this->individualWith([
            'SNOTE' => $this->factStub('Shared note', '', '', '@N3@'),
        ]);

        $facts = $resolver->factsFor($individual, true);

        self::assertSame('', $facts[2]?->value);
    }

    /**
     * Only a value that is a pointer as a whole is resolved. A text that contains an at sign stays as
     * it is, whether the pointer sits in the middle, at the start or at the end of it, and so does a
     * value of a fact that has a link target but no pointer value.
     *
     * @param string $text The value of the fact
     *
     * @return void
     */
    #[Test]
    #[DataProvider('textWithAnAtSignProvider')]
    public function factsForKeepsATextValueThatContainsAnAtSign(string $text): void
    {
        $record = self::createStub(GedcomRecord::class);
        $record->method('fullName')->willReturn('Must not be used');
        $record->method('canShowName')->willReturn(true);

        $resolver   = $this->resolverWithTags('_MILT');
        $individual = $this->individualWith([
            '_MILT' => $this->factStub('Military service', '', '', $text, $record),
        ]);

        $facts = $resolver->factsFor($individual, true);

        self::assertSame($text, $facts[2]?->value);
    }

    /**
     * Texts with a pointer-shaped token that has text around it, which must stay as they are.
     *
     * @return array<string, array{string}>
     */
    public static function textWithAnAtSignProvider(): array
    {
        return [
            'pointer in the middle of a text' => ['Lives @N3@ here'],
            'text after a pointer'            => ['@N3@ trailing'],
            'text before a pointer'           => ['leading @N3@'],
        ];
    }

    /**
     * A pointer to a record that exists but that the viewer may not see gives no value either, so the
     * placeholder name of a private record never reaches the chart.
     *
     * @return void
     */
    #[Test]
    public function factsForGivesNoValueForAPointerToARecordTheViewerMayNotSee(): void
    {
        $record = self::createStub(GedcomRecord::class);
        $record->method('fullName')->willReturn('Private');
        $record->method('canShowName')->willReturn(false);

        $resolver   = $this->resolverWithTags('SNOTE');
        $individual = $this->individualWith([
            'SNOTE' => $this->factStub('Shared note', '', '', '@N3@', $record),
        ]);

        $facts = $resolver->factsFor($individual, true);

        self::assertSame('', $facts[2]?->value);
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

    /**
     * @return array<string, array{string}>
     */
    public static function familyTagProvider(): array
    {
        return [
            'marriage'            => ['MARR'],
            'marriage, no record' => ['_NMR'],
            'divorce'             => ['DIV'],
            'annulment'           => ['ANUL'],
            'separation'          => ['_SEPR'],
        ];
    }

    /**
     * A marriage or divorce belongs to the family, so every family of the person as spouse gives one entry
     * with the name of the other spouse, in the order of the families.
     *
     * @param string $tag The family tag in the preference
     *
     * @return void
     */
    #[Test]
    #[DataProvider('familyTagProvider')]
    public function factsForGivesOneEntryPerFamilyForAFamilyTag(string $tag): void
    {
        $resolver   = $this->resolverWithTags($tag);
        $individual = $this->individualWith(
            [],
            [
                $this->familyWith([$tag => $this->factStub('Event', '1 MAY 1900', 'Berlin, Germany')], '<span>Anna Schmidt</span>'),
                $this->familyWith([$tag => $this->factStub('Event', '2 JUN 1920', 'Paris, France')], '<span>Marie Curie</span>'),
            ]
        );

        $facts = $resolver->factsFor($individual, true);

        self::assertCount(4, $facts, 'birth, death and one entry per family');
        self::assertEquals(
            [
                new FactView(
                    tag: $tag,
                    label: 'Event',
                    date: '01.05.1900',
                    place: 'Berlin, Germany',
                    placeFull: 'Berlin, Germany',
                    value: '',
                    partner: 'Anna Schmidt',
                    sortDay: 2415141,
                ),
                new FactView(
                    tag: $tag,
                    label: 'Event',
                    date: '02.06.1920',
                    place: 'Paris, France',
                    placeFull: 'Paris, France',
                    value: '',
                    partner: 'Marie Curie',
                    sortDay: 2422478,
                ),
            ],
            [$facts[2], $facts[3]]
        );
    }

    /**
     * A date that webtrees does not accept as usable has the sort day zero, even when one end of it is a
     * real day. The empty date alone cannot tell this apart, as its earliest day is zero anyway.
     */
    #[Test]
    public function factsForGivesTheSortDayZeroForARangeWithAnUnusableEnd(): void
    {
        $resolver   = $this->resolverWithTags('OCCU');
        $individual = $this->individualWith([
            'OCCU' => $this->factStub('Occupation', 'BET 1900 AND xyz', '', 'Clerk'),
        ]);

        $facts = $resolver->factsFor($individual, true);

        self::assertSame(0, $facts[2]?->sortDay);
    }

    /**
     * A family tag followed by a person tag. The entries of the family tag follow each other and the
     * later tag moves on by the number of additional entries, so a consumer finds it by its tag.
     */
    #[Test]
    public function factsForKeepsTheEntryOfALaterTagBehindSeveralFamilyEntries(): void
    {
        $resolver   = $this->resolverWithTags('MARR,OCCU');
        $individual = $this->individualWith(
            ['OCCU' => $this->factStub('Occupation', '', '', 'Clerk')],
            [
                $this->familyWith(['MARR' => $this->factStub('Marriage', '1 MAY 1900', 'Berlin, Germany')], 'Anna Schmidt'),
                $this->familyWith(['MARR' => $this->factStub('Marriage', '2 JUN 1920', 'Paris, France')], 'Marie Curie'),
            ]
        );

        $facts = $resolver->factsFor($individual, true);

        self::assertCount(5, $facts, 'birth, death, two marriages and the occupation');
        self::assertSame(
            ['MARR', 'MARR', 'OCCU'],
            [$facts[2]?->tag, $facts[3]?->tag, $facts[4]?->tag]
        );
        self::assertSame('Marie Curie', $facts[3]?->partner);
        self::assertSame('Clerk', $facts[4]?->value);
    }

    /**
     * A family without the fact adds no entry, and a person without any such fact keeps one empty
     * entry, so the number of entries never falls below the number of tags.
     *
     * @return void
     */
    #[Test]
    public function factsForKeepsOneNullEntryWhenNoFamilyHasTheFact(): void
    {
        $resolver = $this->resolverWithTags('MARR');

        $withoutFamily = $this->individualWith([]);
        $withEmptyOne  = $this->individualWith([], [$this->familyWith([], 'Anna Schmidt')]);

        self::assertSame([null, null, null], $resolver->factsFor($withoutFamily, true));
        self::assertSame([null, null, null], $resolver->factsFor($withEmptyOne, true));
    }

    /**
     * Only the families of the person count. A fact of the person with the same tag is not read as a
     * marriage, and a family without a visible partner gives an empty partner.
     *
     * @return void
     */
    #[Test]
    public function factsForReadsAFamilyTagFromTheFamiliesOnly(): void
    {
        $resolver   = $this->resolverWithTags('MARR');
        $individual = $this->individualWith(
            ['MARR' => $this->factStub('Marriage', '1 JAN 1800', 'Nowhere')],
            [$this->familyWith(['MARR' => $this->factStub('Marriage', '5 MAY 1950', 'Wurzen, Germany')], null)]
        );

        $facts = $resolver->factsFor($individual, true);

        self::assertCount(3, $facts, 'birth, death and the one entry of the family tag');
        self::assertInstanceOf(FactView::class, $facts[2]);
        self::assertSame('05.05.1950', $facts[2]->date);
        self::assertSame('', $facts[2]->partner);
    }

    /**
     * An optional tag of the person is still one entry and carries no partner.
     *
     * @return void
     */
    #[Test]
    public function factsForGivesAnOptionalFactOfThePersonNoPartner(): void
    {
        $resolver   = $this->resolverWithTags('OCCU');
        $individual = $this->individualWith(['OCCU' => $this->factStub('Occupation', '', '', 'Smith')]);

        $facts = $resolver->factsFor($individual, true);

        self::assertInstanceOf(FactView::class, $facts[2]);
        self::assertSame('', $facts[2]->partner);
    }
}
