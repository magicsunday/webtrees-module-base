<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Double;

use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use MagicSunday\Webtrees\ModuleBase\Contract\ModuleAssetUrlInterface;
use PHPUnit\Framework\MockObject\Stub;

/**
 * Builds the module stub that a chart facade is given. It is a custom module that also reports asset URLs. A test
 * case composes it, because the stubs come from the test case's own factory.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
trait CreatesModuleStubs
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
}
