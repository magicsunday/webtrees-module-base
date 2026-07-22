<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Traits;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Tree;
use MagicSunday\Webtrees\ModuleBase\Test\Double\ChartModuleDouble;
use MagicSunday\Webtrees\ModuleBase\Traits\ModuleChartTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Pins the chart URL the trait builds for an individual: the consuming module's
 * ROUTE_DEFAULT is used, the individual contributes xref and tree, and caller
 * parameters are added — but cannot displace those two.
 *
 * The route() helper that turns the route name and parameters into a URL is
 * webtrees', not this trait's, so it is mocked away at the trait's own seam
 * (buildRouteUrl); the assertions are on what the trait hands to that seam.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversTrait(ModuleChartTrait::class)]
final class ModuleChartTraitTest extends TestCase
{
    /**
     * Builds a chart module composing the trait, with its route-resolving seam
     * mocked so the URL construction can be asserted in isolation.
     *
     * @return ChartModuleDouble&MockObject
     */
    private function createModule(): ChartModuleDouble&MockObject
    {
        return $this->getMockBuilder(ChartModuleDouble::class)
            ->onlyMethods(['buildRouteUrl'])
            ->getMock();
    }

    /**
     * Builds an individual whose xref and tree name land in the URL.
     *
     * @param string $xref
     * @param string $treeName
     *
     * @return Individual
     */
    private function createIndividual(string $xref, string $treeName): Individual
    {
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn($treeName);

        $individual = self::createStub(Individual::class);
        $individual->method('xref')->willReturn($xref);
        $individual->method('tree')->willReturn($tree);

        return $individual;
    }

    /**
     * The composition is the contract: webtrees' own chart trait supplies
     * chartMenu(), which chartBoxMenu() delegates to.
     */
    #[Test]
    public function composesWebtreesOwnChartTrait(): void
    {
        self::assertContains(
            \Fisharebest\Webtrees\Module\ModuleChartTrait::class,
            (new ReflectionClass(ModuleChartTrait::class))->getTraitNames(),
        );
    }

    /**
     * The individual identifies itself in the URL through its xref and its
     * tree, under the route the consuming module declares — and the resolved
     * URL is handed straight back to the caller.
     */
    #[Test]
    public function chartUrlAddressesTheIndividualUnderTheModulesOwnRoute(): void
    {
        $module = $this->createModule();
        $module->expects(self::once())
            ->method('buildRouteUrl')
            ->with('my-chart', ['xref' => 'X17', 'tree' => 'demo-tree'])
            ->willReturn('/resolved-url');

        self::assertSame(
            '/resolved-url',
            $module->chartUrl($this->createIndividual('X17', 'demo-tree')),
        );
    }

    /**
     * Extra parameters — layout or generation toggles, say — travel to the
     * route alongside the individual's own.
     */
    #[Test]
    public function chartUrlCarriesAdditionalParameters(): void
    {
        $module = $this->createModule();
        $module->expects(self::once())
            ->method('buildRouteUrl')
            ->with(
                'my-chart',
                [
                    'xref'        => 'X17',
                    'tree'        => 'demo-tree',
                    'generations' => 4,
                    'layout'      => 'left',
                ]
            )
            ->willReturn('/resolved-url');

        $module->chartUrl(
            $this->createIndividual('X17', 'demo-tree'),
            [
                'generations' => 4,
                'layout'      => 'left',
            ]
        );
    }

    /**
     * The individual's own keys win: the trait unions its array onto the
     * caller's rather than merging, so a caller cannot point the URL at a
     * different record than the individual it was handed.
     */
    #[Test]
    public function callerParametersCannotDisplaceTheIndividual(): void
    {
        $module = $this->createModule();
        $module->expects(self::once())
            ->method('buildRouteUrl')
            ->with('my-chart', ['xref' => 'X17', 'tree' => 'demo-tree'])
            ->willReturn('/resolved-url');

        $module->chartUrl(
            $this->createIndividual('X17', 'demo-tree'),
            [
                'xref' => 'X99',
                'tree' => 'other-tree',
            ]
        );
    }
}
