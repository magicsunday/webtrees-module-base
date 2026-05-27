<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use MagicSunday\Webtrees\ModuleBase\Facade\ModuleAwareDataFacadeTrait;
use MagicSunday\Webtrees\ModuleBase\Facade\RouteAwareDataFacadeTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

use function trait_exists;

/**
 * Structural coverage for the lightweight DataFacade trait used by chart
 * modules that need only the owning module reference (no route helpers).
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class ModuleAwareDataFacadeTraitTest extends TestCase
{
    /**
     * Verifies the new lightweight facade trait is loadable under its PSR-4 path.
     */
    #[Test]
    public function traitExists(): void
    {
        self::assertTrue(trait_exists(ModuleAwareDataFacadeTrait::class));
    }

    /**
     * Pins the public API of setModule(): one typed parameter and a static
     * return, so chart-module consumers can keep their fluent setter chains.
     */
    #[Test]
    public function setModuleAcceptsModuleAndReturnsStatic(): void
    {
        $method = new ReflectionMethod(ModuleAwareDataFacadeTrait::class, 'setModule');

        self::assertTrue($method->isPublic());
        self::assertCount(
            1,
            $method->getParameters(),
            'setModule takes exactly the module reference; further deps belong in separate setters',
        );

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

    /**
     * Locks isRtl() as a trait-private helper returning bool so external
     * callers reach for TextDirection::isRtl() directly instead of bypassing
     * the trait surface.
     */
    #[Test]
    public function isRtlIsPrivateAndReturnsBool(): void
    {
        $reflection = new ReflectionClass(ModuleAwareDataFacadeTrait::class);

        self::assertTrue($reflection->hasMethod('isRtl'));

        $method = $reflection->getMethod('isRtl');

        self::assertTrue($method->isPrivate());

        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('bool', $returnType->getName());
    }

    /**
     * Guards the trait-split contract: RouteAwareDataFacadeTrait must keep
     * composing ModuleAwareDataFacadeTrait so existing consumers continue
     * to get setModule()/isRtl() when they only opt into RouteAware.
     */
    #[Test]
    public function routeAwareTraitComposesModuleAwareTrait(): void
    {
        $reflection = new ReflectionClass(RouteAwareDataFacadeTrait::class);

        self::assertContains(
            ModuleAwareDataFacadeTrait::class,
            $reflection->getTraitNames(),
        );
    }
}
