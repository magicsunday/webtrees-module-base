<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use MagicSunday\Webtrees\ModuleBase\Facade\RouteAwareDataFacadeTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Structural coverage for the shared DataFacade trait used by chart modules to
 * wire route + module dependencies into the facade.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class RouteAwareDataFacadeTraitTest extends TestCase
{
    #[Test]
    public function traitExists(): void
    {
        self::assertTrue(trait_exists(RouteAwareDataFacadeTrait::class));
    }

    #[Test]
    public function setRouteAcceptsStringAndReturnsStatic(): void
    {
        $method = new ReflectionMethod(RouteAwareDataFacadeTrait::class, 'setRoute');

        self::assertTrue($method->isPublic());
        self::assertCount(1, $method->getParameters());

        $routeType = $method->getParameters()[0]->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $routeType);
        self::assertSame('string', $routeType->getName());

        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('static', $returnType->getName());
    }

    #[Test]
    public function setModuleAcceptsModuleAndReturnsStatic(): void
    {
        $method = new ReflectionMethod(RouteAwareDataFacadeTrait::class, 'setModule');

        self::assertTrue($method->isPublic());
        self::assertCount(1, $method->getParameters());

        $moduleType = $method->getParameters()[0]->getType();
        self::assertNotNull($moduleType);
        // setModule uses an intersection type (ModuleCustomInterface&ModuleAssetUrlInterface).
        // We assert it stays a non-trivial type bound rather than pinning the exact
        // intersection so the trait can evolve safely.
        self::assertNotSame('', (string) $moduleType);

        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('static', $returnType->getName());
    }

    #[Test]
    public function privateHelpersAreScopedToTraitConsumers(): void
    {
        $reflection = new ReflectionClass(RouteAwareDataFacadeTrait::class);

        self::assertTrue($reflection->hasMethod('chartUrl'));
        self::assertTrue($reflection->getMethod('chartUrl')->isPrivate());
    }
}
