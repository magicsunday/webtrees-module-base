<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Facade;

use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use MagicSunday\Webtrees\ModuleBase\Contract\ModuleAssetUrlInterface;
use MagicSunday\Webtrees\ModuleBase\Facade\ModuleAwareDataFacadeTrait;
use MagicSunday\Webtrees\ModuleBase\Facade\RouteAwareDataFacadeTrait;
use MagicSunday\Webtrees\ModuleBase\Test\Double\CreatesIndividualStubs;
use MagicSunday\Webtrees\ModuleBase\Test\Double\RouteAwareFacadeDouble;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Pins the chart URL the route-aware trait builds for an individual. The route the facade was
 * given is used, the individual contributes its xref and tree, caller parameters are added but
 * cannot displace those two, and the module-aware behaviour of the base trait is part of it.
 *
 * The route() helper that turns the route name and parameters into a URL is webtrees', not this
 * trait's, so it is mocked away at the trait's own seam (buildRouteUrl). The assertions are on
 * what the trait hands to that seam.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversTrait(RouteAwareDataFacadeTrait::class)]
#[UsesTrait(ModuleAwareDataFacadeTrait::class)]
final class RouteAwareDataFacadeTraitTest extends TestCase
{
    use CreatesIndividualStubs;

    /**
     * Builds a facade composing the trait, with its route-resolving seam mocked so the URL
     * construction can be asserted in isolation.
     *
     * @return RouteAwareFacadeDouble&MockObject
     */
    private function createFacade(): RouteAwareFacadeDouble&MockObject
    {
        return $this->getMockBuilder(RouteAwareFacadeDouble::class)
            ->onlyMethods(['buildRouteUrl'])
            ->getMock();
    }

    /**
     * setRoute() hands the facade back, so the route can be set in the middle of a call chain.
     */
    #[Test]
    public function setRouteReturnsTheFacadeForChaining(): void
    {
        $facade = new RouteAwareFacadeDouble();

        self::assertSame($facade, $facade->setRoute('my-chart'));
    }

    /**
     * The individual identifies itself in the URL through its xref and its tree, under the route
     * the facade was given, and the resolved URL is handed straight back to the caller.
     */
    #[Test]
    public function chartUrlAddressesTheIndividualUnderTheGivenRoute(): void
    {
        $facade = $this->createFacade();
        $facade->expects(self::once())
            ->method('buildRouteUrl')
            ->with('my-chart', ['xref' => 'X17', 'tree' => 'demo-tree'])
            ->willReturn('/resolved-url');

        self::assertSame(
            '/resolved-url',
            $facade->setRoute('my-chart')->urlFor($this->createIndividual('X17', 'demo-tree')),
        );
    }

    /**
     * A route that is set again replaces the earlier one, so the facade does not keep a fixed
     * route.
     */
    #[Test]
    public function aLaterRouteReplacesTheEarlierOne(): void
    {
        $facade = $this->createFacade();
        $facade->expects(self::once())
            ->method('buildRouteUrl')
            ->with('second-chart', ['xref' => 'X17', 'tree' => 'demo-tree'])
            ->willReturn('/resolved-url');

        $facade->setRoute('first-chart');
        $facade->setRoute('second-chart');
        $facade->urlFor($this->createIndividual('X17', 'demo-tree'));
    }

    /**
     * Extra parameters, a layout or generation toggle say, travel to the route alongside the
     * individual's own.
     */
    #[Test]
    public function chartUrlCarriesAdditionalParameters(): void
    {
        $facade = $this->createFacade();
        $facade->expects(self::once())
            ->method('buildRouteUrl')
            ->with(
                'my-chart',
                [
                    'xref'        => 'X17',
                    'tree'        => 'demo-tree',
                    'generations' => 4,
                    'layout'      => 'left',
                ],
            )
            ->willReturn('/resolved-url');

        $facade->setRoute('my-chart')->urlFor(
            $this->createIndividual('X17', 'demo-tree'),
            [
                'generations' => 4,
                'layout'      => 'left',
            ],
        );
    }

    /**
     * The xref and the tree belong to the individual. A caller parameter with the same name
     * cannot displace them, so a request value never points the URL at another person or tree.
     */
    #[Test]
    public function callerParametersCannotDisplaceTheXrefOrTheTree(): void
    {
        $facade = $this->createFacade();
        $facade->expects(self::once())
            ->method('buildRouteUrl')
            ->with(
                'my-chart',
                [
                    'xref'   => 'X17',
                    'tree'   => 'demo-tree',
                    'layout' => 'left',
                ],
            )
            ->willReturn('/resolved-url');

        $facade->setRoute('my-chart')->urlFor(
            $this->createIndividual('X17', 'demo-tree'),
            [
                'xref'   => 'X99',
                'tree'   => 'other-tree',
                'layout' => 'left',
            ],
        );
    }

    /**
     * The route-aware trait includes the module-aware one, so the facade takes the owning module
     * through it and the trait hands the facade back for chaining.
     */
    #[Test]
    public function includesTheModuleInjectionOfTheBaseTrait(): void
    {
        $module = self::createStubForIntersectionOfInterfaces([
            ModuleCustomInterface::class,
            ModuleAssetUrlInterface::class,
        ]);

        $facade = new RouteAwareFacadeDouble();

        self::assertSame($facade, $facade->setModule($module));
        self::assertSame($module, $facade->injectedModule());
    }
}
