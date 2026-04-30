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

/**
 * Resolves which facts to render in a chart's person boxes, mirroring the
 * webtrees core chart-box template: a BIRT-equivalent event (BIRT / CHR /
 * BAPM) is always shown, followed optionally by the tree-level CHART_BOX_TAGS
 * preference — the same list the core uses — and finally a DEAT-equivalent
 * event (DEAT / BURI / CREM).
 *
 * Callers pass {@see showAdditional()} from a per-chart form toggle so users
 * can globally hide the extra facts without editing tree settings. Callers
 * may also pass {@see excludeTags()} to remove inappropriate tags for a
 * given chart — e.g. the pedigree-chart is ancestors-only and filters MARR.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class FactResolver
{
    public function __construct(
        private Tree $tree,
    ) {
    }

    /**
     * Returns the tag list actually rendered for each box in the chart, in
     * order: always BIRT-equivalent first, optionally the tree-configured
     * extras, always DEAT-equivalent last. This count drives the chart's
     * uniform box height so all boxes stay aligned even when a given person
     * lacks a particular fact.
     *
     * @param bool         $showAdditional Whether the chart's "Show additional facts" toggle is on
     * @param list<string> $excludeTags    Tags to filter out of the optional list (e.g. ['MARR'] for ancestor charts)
     *
     * @return list<string>
     */
    public function effectiveTags(bool $showAdditional, array $excludeTags = []): array
    {
        // BIRT and DEAT come first (stacked directly under the name),
        // optional tags come after — the renderer may add a visual gap
        // between the date block and the optional fact block.
        $tags = [self::BIRTH_PLACEHOLDER, self::DEATH_PLACEHOLDER];

        if ($showAdditional) {
            foreach ($this->optionalTags($excludeTags) as $tag) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * Returns the extracted fact views for one individual, aligned with
     * {@see effectiveTags()}. Positions where the individual has no
     * matching fact are returned as null so the renderer can reserve
     * space without drawing anything.
     *
     * @param Individual   $individual
     * @param bool         $showAdditional
     * @param list<string> $excludeTags
     *
     * @return list<array{tag: string, label: string, date: string, place: string, value: string}|null>
     */
    public function factsFor(Individual $individual, bool $showAdditional, array $excludeTags = []): array
    {
        $views = [];

        $views[] = $this->firstOfGroup($individual, Gedcom::BIRTH_EVENTS);
        $views[] = $this->firstOfGroup($individual, Gedcom::DEATH_EVENTS);

        if ($showAdditional) {
            foreach ($this->optionalTags($excludeTags) as $tag) {
                $views[] = $this->firstWithTag($individual, $tag);
            }
        }

        return $views;
    }

    /**
     * Returns the tree-level optional tag list (CHART_BOX_TAGS) with BIRT-
     * and DEAT-equivalent tags filtered out, plus any caller-supplied
     * exclusions.
     *
     * @param list<string> $excludeTags
     *
     * @return list<string>
     */
    public function optionalTags(array $excludeTags = []): array
    {
        preg_match_all('/\w+/', $this->tree->getPreference(self::PREFERENCE_CHART_BOX_TAGS), $matches);

        $always = array_merge(Gedcom::BIRTH_EVENTS, Gedcom::DEATH_EVENTS);

        return array_values(array_filter(
            $matches[0],
            static fn (string $tag): bool => !in_array($tag, $always, true)
                && !in_array($tag, $excludeTags, true)
        ));
    }

    /**
     * @param list<string> $group
     *
     * @return array{tag: string, label: string, date: string, place: string, value: string}|null
     */
    private function firstOfGroup(Individual $individual, array $group): ?array
    {
        foreach ($group as $tag) {
            $view = $this->firstWithTag($individual, $tag);

            if ($view !== null) {
                return $view;
            }
        }

        return null;
    }

    /**
     * @return array{tag: string, label: string, date: string, place: string, value: string}|null
     */
    private function firstWithTag(Individual $individual, string $tag): ?array
    {
        $fact = $individual->facts([$tag])->first();

        if (!$fact instanceof Fact) {
            return null;
        }

        return [
            'tag'   => $tag,
            'label' => $fact->label(),
            'date'  => strip_tags($fact->date()->display()),
            'place' => strip_tags($fact->place()->shortName()),
            'value' => strip_tags($fact->value()),
        ];
    }

    /**
     * Placeholder used in {@see effectiveTags()} so the BIRT row's position
     * is preserved regardless of which concrete birth-equivalent tag each
     * individual has.
     */
    public const string BIRTH_PLACEHOLDER = 'BIRT';

    /**
     * Placeholder used in {@see effectiveTags()} so the DEAT row's position
     * is preserved regardless of which concrete death-equivalent tag each
     * individual has.
     */
    public const string DEATH_PLACEHOLDER = 'DEAT';

    /**
     * Name of the tree-level preference holding the optional-tag CSV.
     */
    private const string PREFERENCE_CHART_BOX_TAGS = 'CHART_BOX_TAGS';
}
