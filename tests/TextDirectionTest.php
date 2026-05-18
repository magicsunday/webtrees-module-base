<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use MagicSunday\Webtrees\ModuleBase\Support\TextDirection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Structural coverage for the shared RTL detection helper.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class TextDirectionTest extends TestCase
{
    #[Test]
    public function classExistsAndIsFinal(): void
    {
        self::assertTrue(class_exists(TextDirection::class));

        $reflection = new ReflectionClass(TextDirection::class);

        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function isRtlIsPublicStaticAndAcceptsString(): void
    {
        $method = new ReflectionMethod(TextDirection::class, 'isRtl');

        self::assertTrue($method->isPublic());
        self::assertTrue($method->isStatic());
        self::assertCount(1, $method->getParameters());

        $parameterType = $method->getParameters()[0]->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $parameterType);
        self::assertSame('string', $parameterType->getName());

        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('bool', $returnType->getName());
    }
}
