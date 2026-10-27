<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Processor;

use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Tree;
use MagicSunday\Webtrees\ModuleBase\Model\FactView;
use MagicSunday\Webtrees\ModuleBase\Model\PlaceFormatSpec;
use MagicSunday\Webtrees\ModuleBase\Support\CompactDateFormat;
use MagicSunday\Webtrees\ModuleBase\Support\Locale\IsoCountryMap;
use MagicSunday\Webtrees\ModuleBase\Support\PlainText;

use function array_map;
use function in_array;
use function preg_match;
use function preg_match_all;
use function strtoupper;

/**
 * Resolves which facts a chart box shows and the text of each one.
 *
 * A chart box has fixed rows, so the answer is a list of slots. Slot 0 is the first existing
 * birth equivalent (BIRT, CHR, BAPM) and slot 1 the first existing death equivalent (DEAT, BURI,
 * CREM), each null when the person has no visible fact for it. When the caller asks for the optional
 * facts, the optional tags of the tree preference "Other facts to show in charts" follow, so the
 * first two rows keep their meaning for every person and a chart can reserve them as a fixed block.
 * The tags of the preference are upper-cased and de-duplicated, and the birth and death
 * equivalents are left out of them.
 *
 * A marriage or a divorce is a fact of the family and not of the person. For a tag of that kind the
 * families of the person as spouse are read, and every family with the fact gives one entry that
 * names the other spouse, so a person with several marriages gets several entries.
 *
 * The administration offers only facts of a person for the preference. When the caller asks for the
 * optional facts, the marriage and the divorce are therefore added unless the preference names the
 * tag itself. The added marriage follows the death slot and the added divorce follows the marriage,
 * wherever the marriage stands. Each gives one entry per family that has the fact and no null entry
 * otherwise. A tag of that kind that the preference names keeps its own place and gives a single
 * null entry when no family has the fact, and a preference that names both tags keeps its own order
 * for them.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class FactResolver
{
    /**
     * Name of the tree preference that holds the list of optional tags.
     */
    private const string PREFERENCE_CHART_BOX_TAGS = 'CHART_BOX_TAGS';

    /**
     * Tag of the marriage, which is added to the optional facts without a listing in the tree
     * preference.
     */
    private const string TAG_MARRIAGE = 'MARR';

    /**
     * Tag of the divorce, which is added to the optional facts without a listing in the tree
     * preference.
     */
    private const string TAG_DIVORCE = 'DIV';

    /**
     * @param Tree            $tree              The tree whose preference lists the optional tags
     * @param PlaceFormatSpec $placeFormat       How the place of a fact is shortened
     * @param IsoCountryMap   $countryMap        Resolver for the place styles that resolve a country segment
     * @param string          $compactDateFormat The webtrees calendar format string for a complete date, defaults
     *                                           to CompactDateFormat::FALLBACK (locale-neutral ISO 8601)
     */
    public function __construct(
        private Tree $tree,
        private PlaceFormatSpec $placeFormat,
        private IsoCountryMap $countryMap,
        private string $compactDateFormat = CompactDateFormat::FALLBACK,
    ) {
    }

    /**
     * Returns the optional tags of the tree preference in their order, upper case and without
     * duplicates. The birth and death equivalents are left out because they have their own slots.
     *
     * @return list<string>
     */
    public function optionalTags(): array
    {
        preg_match_all('/\w+/', $this->tree->getPreference(self::PREFERENCE_CHART_BOX_TAGS), $matches);

        $skipped = array_map(
            strtoupper(...),
            [...Gedcom::BIRTH_EVENTS, ...Gedcom::DEATH_EVENTS]
        );

        $tags = [];

        foreach ($matches[0] as $match) {
            $tag = strtoupper($match);

            if (
                !in_array($tag, $skipped, true)
                && !in_array($tag, $tags, true)
            ) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * Returns the facts of one individual, one slot per row of a chart box. The first slot is the
     * birth, the second the death. With the optional facts, the added marriage and divorce and the
     * optional tags in the order of {@see self::optionalTags()} follow. A marriage or divorce tag
     * gives one entry per family with the fact, so the later tags move on by the number of additional
     * entries. The class docblock holds the rules for a preference that names the marriage or the
     * divorce itself.
     *
     * @param Individual $individual     The individual to describe
     * @param bool       $showAdditional Whether the chart shows the optional facts
     *
     * @return list<FactView|null> Null where the individual has no visible fact for a slot, the added
     *                             marriage and divorce give no null entry
     */
    public function factsFor(Individual $individual, bool $showAdditional): array
    {
        $places = new PlaceProcessor($individual, $this->placeFormat, $this->countryMap);

        $views = [
            $this->firstOfGroup($individual, Gedcom::BIRTH_EVENTS, $places),
            $this->firstOfGroup($individual, Gedcom::DEATH_EVENTS, $places),
        ];

        if ($showAdditional) {
            $optionalTags = $this->optionalTags();

            foreach ([self::TAG_MARRIAGE, self::TAG_DIVORCE] as $tag) {
                // A divorce that follows a marriage of the preference is added behind that marriage below.
                if (
                    in_array($tag, $optionalTags, true)
                    || (($tag === self::TAG_DIVORCE) && in_array(self::TAG_MARRIAGE, $optionalTags, true))
                ) {
                    continue;
                }

                $views = [...$views, ...$this->presentViewsOfTag($individual, $tag, $places)];
            }

            foreach ($optionalTags as $tag) {
                $views = [...$views, ...$this->viewsOfTag($individual, $tag, $places)];

                if (
                    ($tag === self::TAG_MARRIAGE)
                    && !in_array(self::TAG_DIVORCE, $optionalTags, true)
                ) {
                    $views = [...$views, ...$this->presentViewsOfTag($individual, self::TAG_DIVORCE, $places)];
                }
            }
        }

        return $views;
    }

    /**
     * Returns the entries of a tag that the individual has a visible fact for, and none otherwise.
     *
     * @param Individual     $individual The individual to describe
     * @param string         $tag        The GEDCOM tag to look for
     * @param PlaceProcessor $places     The place formatter of the individual
     *
     * @return list<FactView> The entries with a visible fact, empty when the individual has none
     */
    private function presentViewsOfTag(Individual $individual, string $tag, PlaceProcessor $places): array
    {
        $views = [];

        foreach ($this->viewsOfTag($individual, $tag, $places) as $view) {
            if ($view instanceof FactView) {
                $views[] = $view;
            }
        }

        return $views;
    }

    /**
     * Returns the entries of one optional tag. A tag of the person gives one entry, a tag of a family
     * gives one entry per family of the person as spouse that has the fact.
     *
     * @param Individual     $individual The individual to describe
     * @param string         $tag        The GEDCOM tag to look for
     * @param PlaceProcessor $places     The place formatter of the individual
     *
     * @return non-empty-list<FactView|null> Null for a tag without any visible fact
     */
    private function viewsOfTag(Individual $individual, string $tag, PlaceProcessor $places): array
    {
        if (!in_array($tag, [...Gedcom::MARRIAGE_EVENTS, ...Gedcom::DIVORCE_EVENTS], true)) {
            return [$this->viewOfTag($individual, $tag, $places)];
        }

        $views = [];

        foreach ($individual->spouseFamilies() as $family) {
            $fact = $family->facts([$tag])->first();

            if (!$fact instanceof Fact) {
                continue;
            }

            $spouse = $family->spouse($individual);

            $views[] = $this->viewOfFact(
                $tag,
                $fact,
                $places,
                $spouse instanceof Individual ? PlainText::fromMarkup($spouse->fullName()) : ''
            );
        }

        return $views === [] ? [null] : $views;
    }

    /**
     * Returns the view of the first tag of the group the individual has a visible fact for.
     *
     * @param Individual     $individual The individual to describe
     * @param list<string>   $group      The tags of one group in order of preference
     * @param PlaceProcessor $places     The place formatter of the individual
     */
    private function firstOfGroup(Individual $individual, array $group, PlaceProcessor $places): ?FactView
    {
        foreach ($group as $tag) {
            $view = $this->viewOfTag($individual, $tag, $places);

            if ($view instanceof FactView) {
                return $view;
            }
        }

        return null;
    }

    /**
     * Returns the view of the first visible fact of a tag.
     *
     * @param Individual     $individual The individual to describe
     * @param string         $tag        The GEDCOM tag to look for
     * @param PlaceProcessor $places     The place formatter of the individual
     *
     * @return FactView|null Null when the individual has no visible fact with this tag
     */
    private function viewOfTag(Individual $individual, string $tag, PlaceProcessor $places): ?FactView
    {
        $fact = $individual->facts([$tag])->first();

        if (!$fact instanceof Fact) {
            return null;
        }

        return $this->viewOfFact($tag, $fact, $places);
    }

    /**
     * Returns the value of a fact as text for a reader. A fact such as a shared note stores a pointer to
     * a record as its value, which says nothing to a reader, so the value is the plain-text name of the
     * record that the pointer names. A pointer to a record that the viewer cannot get gives an empty
     * value, so the raw pointer never reaches a chart. A text that equals a pointer as a whole is read
     * as a pointer too, because the file import of webtrees stores the doubled at sign of a text as a
     * single one, so nothing tells the two apart and such a text is rare.
     *
     * @param Fact $fact The visible fact
     *
     * @return string
     */
    private function valueOfFact(Fact $fact): string
    {
        $value = $fact->value();

        if (preg_match('/^@' . Gedcom::REGEX_XREF . '@$/', $value) !== 1) {
            return $value;
        }

        $target = $fact->target();

        return (($target instanceof GedcomRecord) && $target->canShowName())
            ? PlainText::fromMarkup($target->fullName())
            : '';
    }

    /**
     * Returns the view of a fact.
     *
     * @param string         $tag     The GEDCOM tag to report
     * @param Fact           $fact    The visible fact
     * @param PlaceProcessor $places  The place formatter of the individual
     * @param string         $partner The name of the other spouse for an event of a family
     */
    private function viewOfFact(string $tag, Fact $fact, PlaceProcessor $places, string $partner = ''): FactView
    {
        return new FactView(
            tag: $tag,
            label: PlainText::fromMarkup($fact->label()),
            date: DateProcessor::formatFactDate($fact->date(), $this->compactDateFormat),
            place: $places->shortPlaceName($fact->place()),
            placeFull: $fact->place()->gedcomName(),
            value: $this->valueOfFact($fact),
            partner: $partner,
            sortDay: $fact->date()->isOK() ? $fact->date()->minimumJulianDay() : 0,
        );
    }
}
