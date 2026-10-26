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

use function array_map;
use function in_array;
use function preg_match_all;
use function strtoupper;

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
     * Returns the optional tags of the tree preference in their order, upper case and without
     * duplicates. The birth and death equivalents are left out because they have their own slots.
     *
     * @return list<string>
     */
    public function optionalTags(): array
    {
        if (preg_match_all('/\w+/', $this->tree->getPreference(self::PREFERENCE_CHART_BOX_TAGS), $matches) === false) {
            return [];
        }

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
     * birth, the second the death and every further slot one optional tag in the order of
     * {@see self::optionalTags()}.
     *
     * @param Individual $individual     The individual to describe
     * @param bool       $showAdditional Whether the chart shows the optional facts
     *
     * @return list<FactView|null> One entry per slot, null where the individual has no visible fact
     */
    public function factsFor(Individual $individual, bool $showAdditional): array
    {
        $places = new PlaceProcessor($individual, $this->placeFormat, $this->countryMap);

        $views = [
            $this->firstOfGroup($individual, Gedcom::BIRTH_EVENTS, $places),
            $this->firstOfGroup($individual, Gedcom::DEATH_EVENTS, $places),
        ];

        if ($showAdditional) {
            foreach ($this->optionalTags() as $tag) {
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
