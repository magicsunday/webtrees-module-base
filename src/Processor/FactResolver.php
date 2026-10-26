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
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Tree;
use MagicSunday\Webtrees\ModuleBase\Model\FactView;
use MagicSunday\Webtrees\ModuleBase\Model\PlaceFormatSpec;
use MagicSunday\Webtrees\ModuleBase\Support\CompactDateFormat;
use MagicSunday\Webtrees\ModuleBase\Support\Locale\IsoCountryMap;
use MagicSunday\Webtrees\ModuleBase\Support\PlainText;

/**
 * Resolves which facts a chart box shows and the text of each one.
 *
 * A chart box has fixed rows, so the answer is a list of slots. Slot 0 is the first existing
 * birth equivalent (BIRT, CHR, BAPM) and slot 1 the first existing death equivalent (DEAT,
 * BURI, CREM). The optional tags of the tree preference "Other facts to show in charts"
 * follow, one slot each. A slot is null when the person has no visible fact for it, so every
 * box of a chart keeps the same rows.
 *
 * The optional slots follow the death slot, so the first two rows keep their meaning for every
 * person and a chart can reserve them as a fixed block. None of the birth and death equivalents
 * is optional, because they have their own slots, and the tags of the preference are upper-cased
 * and de-duplicated.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class FactResolver
{
    /**
     * Placeholder tag of the birth slot, whatever birth equivalent a person has.
     */
    private const string BIRTH_SLOT = 'BIRT';

    /**
     * Placeholder tag of the death slot, whatever death equivalent a person has.
     */
    private const string DEATH_SLOT = 'DEAT';

    /**
     * Name of the tree preference that holds the list of optional tags.
     */
    private const string PREFERENCE_CHART_BOX_TAGS = 'CHART_BOX_TAGS';

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
     * Returns the tag of every slot of a chart box, in order. The count of the list is the number of
     * rows a chart has to reserve for each box.
     *
     * @param bool         $showAdditional Whether the chart shows the optional facts
     * @param list<string> $excludeTags    Tags to leave out of the optional list, for example MARR in an
     *                                     ancestor chart
     *
     * @return list<string>
     */
    public function effectiveTags(bool $showAdditional, array $excludeTags = []): array
    {
        $tags = [self::BIRTH_SLOT, self::DEATH_SLOT];

        if ($showAdditional) {
            foreach ($this->optionalTags($excludeTags) as $tag) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * Returns the optional tags of the tree preference in their order, upper case and without
     * duplicates. The birth and death equivalents are left out because they have their own slots.
     *
     * @param list<string> $excludeTags Tags to leave out as well
     *
     * @return list<string>
     */
    public function optionalTags(array $excludeTags = []): array
    {
        if (preg_match_all('/\w+/', $this->tree->getPreference(self::PREFERENCE_CHART_BOX_TAGS), $matches) === false) {
            return [];
        }

        $skipped = array_map(
            strtoupper(...),
            [...Gedcom::BIRTH_EVENTS, ...Gedcom::DEATH_EVENTS, ...$excludeTags]
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
     * Returns the facts of one individual, aligned with {@see self::effectiveTags()}.
     *
     * @param Individual   $individual     The individual to describe
     * @param bool         $showAdditional Whether the chart shows the optional facts
     * @param list<string> $excludeTags    Tags to leave out of the optional list
     *
     * @return list<FactView|null> One entry per slot, null where the individual has no visible fact
     */
    public function factsFor(Individual $individual, bool $showAdditional, array $excludeTags = []): array
    {
        $places = new PlaceProcessor($individual, $this->placeFormat, $this->countryMap);

        $views = [
            $this->firstOfGroup($individual, Gedcom::BIRTH_EVENTS, $places),
            $this->firstOfGroup($individual, Gedcom::DEATH_EVENTS, $places),
        ];

        if ($showAdditional) {
            foreach ($this->optionalTags($excludeTags) as $tag) {
                $views[] = $this->viewOfTag($individual, $tag, $places);
            }
        }

        return $views;
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

        return new FactView(
            tag: $tag,
            label: PlainText::fromMarkup($fact->label()),
            date: DateProcessor::formatFactDate($fact->date(), $this->compactDateFormat),
            place: $places->shortPlaceName($fact->place()),
            placeFull: $fact->place()->gedcomName(),
            value: $fact->value(),
        );
    }
}
