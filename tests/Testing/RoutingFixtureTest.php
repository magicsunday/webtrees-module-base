<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Testing;

use Fisharebest\Webtrees\Contracts\ContainerInterface;
use Fisharebest\Webtrees\Contracts\RouteFactoryInterface;
use Fisharebest\Webtrees\Registry;
use MagicSunday\Webtrees\ModuleBase\Testing\RoutingFixture;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

use function iterator_count;
use function spl_object_id;

/**
 * Pins the route factory and the container that the routing fixture registers.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(RoutingFixture::class)]
final class RoutingFixtureTest extends TestCase
{
    /**
     * The route factory that was registered before the test, or null when none was.
     */
    private ?RouteFactoryInterface $previousRouteFactory = null;

    /**
     * The container that was registered before the test, or null when none was.
     */
    private ?ContainerInterface $previousContainer = null;

    /**
     * Remembers the registered route factory and container, so the test leaves them as it found them.
     */
    protected function setUp(): void
    {
        $routeFactory = new ReflectionProperty(Registry::class, 'route_factory');
        $container    = new ReflectionProperty(Registry::class, 'container');

        $registeredRouteFactory = $routeFactory->isInitialized() ? $routeFactory->getValue() : null;
        $registeredContainer    = $container->isInitialized() ? $container->getValue() : null;

        $this->previousRouteFactory = $registeredRouteFactory instanceof RouteFactoryInterface ? $registeredRouteFactory : null;
        $this->previousContainer    = $registeredContainer instanceof ContainerInterface ? $registeredContainer : null;
    }

    /**
     * Puts the remembered route factory and container back.
     */
    protected function tearDown(): void
    {
        if ($this->previousRouteFactory instanceof RouteFactoryInterface) {
            Registry::routeFactory($this->previousRouteFactory);
        }

        if ($this->previousContainer instanceof ContainerInterface) {
            Registry::container($this->previousContainer);
        }
    }

    /**
     * A route renders as its name followed by its query, and the route map has no route.
     *
     * @return void
     */
    #[Test]
    public function registersARouteFactoryThatRendersTheNameAndTheQuery(): void
    {
        RoutingFixture::register();

        self::assertSame('/route/chart?xref=X1&generations=4', Registry::routeFactory()->route('chart', ['xref' => 'X1', 'generations' => 4]));
        self::assertSame('/route/plain?', Registry::routeFactory()->route('plain'));
        self::assertSame(0, iterator_count(Registry::routeFactory()->routeMap()));
    }

    /**
     * The registration returns the route map of the route factory, the same one on every call, so
     * a route that the code under test adds to it can be read back.
     *
     * @return void
     */
    #[Test]
    public function returnsTheRouteMapThatTheRouteFactoryHandsOut(): void
    {
        $routeMap = RoutingFixture::register();

        // The ids stand in for the objects, because a failed comparison of the route maps themselves made
        // the run of this directory stall in the buildbox.
        self::assertSame(spl_object_id($routeMap), spl_object_id(Registry::routeFactory()->routeMap()));
        self::assertSame(spl_object_id(Registry::routeFactory()->routeMap()), spl_object_id(Registry::routeFactory()->routeMap()));

        $route = Registry::routeFactory()->routeMap()->get('the-route', '/the/route');

        self::assertSame(spl_object_id($route), spl_object_id($routeMap->getRoute('the-route')));
    }

    /**
     * Each registration hands out a route map of its own, so a route of an earlier test does not stay.
     *
     * @return void
     */
    #[Test]
    public function eachRegistrationHandsOutAnEmptyRouteMapOfItsOwn(): void
    {
        RoutingFixture::register()->get('the-route', '/the/route');

        $routeMap = RoutingFixture::register();

        self::assertSame(0, iterator_count($routeMap));
    }

    /**
     * The container hands out the route factory and the given services, and nothing else.
     *
     * @return void
     */
    #[Test]
    public function registersAContainerThatServesTheRouteFactoryAndTheGivenServices(): void
    {
        $service = $this->aService();

        RoutingFixture::register(['the-service' => $service]);

        $container = Registry::container();

        self::assertTrue($container->has('the-service'));
        self::assertSame($service, $container->get('the-service'));
        self::assertTrue($container->has(RouteFactoryInterface::class));
        $servedRouteFactory = $container->get(RouteFactoryInterface::class);

        self::assertIsObject($servedRouteFactory);
        self::assertSame(spl_object_id(Registry::routeFactory()), spl_object_id($servedRouteFactory));
        self::assertFalse($container->has('another-service'));
    }

    /**
     * Asking for a service that is not registered fails with a message that names it.
     *
     * @return void
     */
    #[Test]
    public function failsForAServiceThatIsNotRegistered(): void
    {
        RoutingFixture::register();

        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessageMatches('/^The container has no service another-service$/');

        Registry::container()->get('another-service');
    }

    /**
     * Setting a service ignores it and returns the container, because the services are fixed.
     *
     * @return void
     */
    #[Test]
    public function ignoresAServiceThatIsSetLater(): void
    {
        RoutingFixture::register();

        $container = Registry::container();

        self::assertSame(spl_object_id($container), spl_object_id($container->set('late-service', $this->aService())));
        self::assertFalse($container->has('late-service'));
    }

    /**
     * A later registration replaces the services of the earlier one.
     *
     * @return void
     */
    #[Test]
    public function aLaterRegistrationReplacesTheServicesOfTheEarlierOne(): void
    {
        RoutingFixture::register(['the-service' => $this->aService()]);
        RoutingFixture::register();

        self::assertFalse(Registry::container()->has('the-service'));
    }

    /**
     * Returns a service object without any behaviour, for the tests that only need an object.
     *
     * @return object
     */
    private function aService(): object
    {
        return new class {
        };
    }
}
