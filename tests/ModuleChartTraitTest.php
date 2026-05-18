<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Menu;
use MagicSunday\Webtrees\ModuleBase\Traits\ModuleChartTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Structural coverage for the shared chart-module trait extracted from the
 * fan/pedigree/descendants chart modules.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class ModuleChartTraitTest extends TestCase
{
    #[Test]
    public function traitExistsAndReusesWebtreesBaseTrait(): void
    {
        self::assertTrue(trait_exists(ModuleChartTrait::class));

        $reflection = new ReflectionClass(ModuleChartTrait::class);

        self::assertContains(
            \Fisharebest\Webtrees\Module\ModuleChartTrait::class,
            $reflection->getTraitNames(),
        );
    }

    #[Test]
    public function chartBoxMenuReturnsMenuOrNull(): void
    {
        $method = new ReflectionMethod(ModuleChartTrait::class, 'chartBoxMenu');

        self::assertTrue($method->isPublic());
        self::assertCount(1, $method->getParameters());

        $parameter     = $method->getParameters()[0];
        $parameterType = $parameter->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $parameterType);
        self::assertSame(Individual::class, $parameterType->getName());

        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame(Menu::class, $returnType->getName());
        self::assertTrue($returnType->allowsNull());
    }

    #[Test]
    public function chartUrlReturnsString(): void
    {
        $method = new ReflectionMethod(ModuleChartTrait::class, 'chartUrl');

        self::assertTrue($method->isPublic());
        self::assertCount(2, $method->getParameters());

        $individualType = $method->getParameters()[0]->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $individualType);
        self::assertSame(Individual::class, $individualType->getName());

        $parameters = $method->getParameters()[1];
        self::assertTrue($parameters->isDefaultValueAvailable());
        self::assertSame([], $parameters->getDefaultValue());

        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('string', $returnType->getName());
    }
}
