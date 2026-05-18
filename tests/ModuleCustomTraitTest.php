<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use MagicSunday\Webtrees\ModuleBase\Traits\ModuleCustomTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Structural coverage for the shared custom-module trait extracted from the
 * fan/pedigree/descendants chart modules.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class ModuleCustomTraitTest extends TestCase
{
    #[Test]
    public function traitExistsAndReusesWebtreesBaseTrait(): void
    {
        self::assertTrue(trait_exists(ModuleCustomTrait::class));

        $reflection = new ReflectionClass(ModuleCustomTrait::class);

        self::assertContains(
            \Fisharebest\Webtrees\Module\ModuleCustomTrait::class,
            $reflection->getTraitNames(),
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function customModuleAccessorProvider(): array
    {
        return [
            'author name'        => ['customModuleAuthorName'],
            'version'            => ['customModuleVersion'],
            'latest version url' => ['customModuleLatestVersionUrl'],
            'latest version'     => ['customModuleLatestVersion'],
            'support url'        => ['customModuleSupportUrl'],
        ];
    }

    #[Test]
    #[DataProvider('customModuleAccessorProvider')]
    public function exposesPublicStringAccessor(string $method): void
    {
        $reflection = new ReflectionMethod(ModuleCustomTrait::class, $method);

        self::assertTrue($reflection->isPublic());
        self::assertCount(0, $reflection->getParameters());

        $returnType = $reflection->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('string', $returnType->getName());
    }

    #[Test]
    public function customTranslationsTakesLanguageAndReturnsArray(): void
    {
        $method = new ReflectionMethod(ModuleCustomTrait::class, 'customTranslations');

        self::assertTrue($method->isPublic());
        self::assertCount(1, $method->getParameters());

        $languageType = $method->getParameters()[0]->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $languageType);
        self::assertSame('string', $languageType->getName());

        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('array', $returnType->getName());
    }
}
