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
use MagicSunday\Webtrees\ModuleBase\Test\Double\ModuleAwareFacadeDouble;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Pins what a chart facade gets from the module-aware trait. setModule() returns the facade for
 * chaining, and the facade then works with the module it was given, the last one when it is set
 * again. The facade is a double that composes the trait the way a consuming module does.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversTrait(ModuleAwareDataFacadeTrait::class)]
final class ModuleAwareDataFacadeTraitTest extends TestCase
{
    /**
     * Builds a module stub that reports a fixed URL for every asset.
     *
     * @param string $assetUrl The URL the module reports
     *
     * @return ModuleCustomInterface&ModuleAssetUrlInterface&Stub
     */
    private function createModule(string $assetUrl): ModuleCustomInterface&ModuleAssetUrlInterface&Stub
    {
        $module = self::createStubForIntersectionOfInterfaces([
            ModuleCustomInterface::class,
            ModuleAssetUrlInterface::class,
        ]);
        $module->method('assetUrl')->willReturn($assetUrl);

        return $module;
    }

    /**
     * setModule() hands the facade back, so a module can be set in the middle of a call chain.
     */
    #[Test]
    public function setModuleReturnsTheFacadeForChaining(): void
    {
        $facade = new ModuleAwareFacadeDouble();

        self::assertSame($facade, $facade->setModule($this->createModule('/assets/a.png')));
    }

    /**
     * The facade works with the module it was given, so an asset URL comes from that module.
     */
    #[Test]
    public function theFacadeReadsAssetUrlsFromTheInjectedModule(): void
    {
        $facade = (new ModuleAwareFacadeDouble())->setModule($this->createModule('/assets/a.png'));

        self::assertSame('/assets/a.png', $facade->assetUrlOf('a.png'));
    }

    /**
     * Setting the module again replaces the earlier one.
     */
    #[Test]
    public function aLaterModuleReplacesTheEarlierOne(): void
    {
        $facade = new ModuleAwareFacadeDouble();
        $facade->setModule($this->createModule('/first/a.png'));
        $facade->setModule($this->createModule('/second/a.png'));

        self::assertSame('/second/a.png', $facade->assetUrlOf('a.png'));
    }
}
