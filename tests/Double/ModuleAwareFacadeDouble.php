<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Double;

use MagicSunday\Webtrees\ModuleBase\Facade\ModuleAwareDataFacadeTrait;

/**
 * A chart data facade as a consuming module writes it when it needs the owning module but no
 * route: it composes the module-aware trait and reads the injected module from its own method.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
class ModuleAwareFacadeDouble
{
    use ModuleAwareDataFacadeTrait;

    /**
     * Reads an asset URL from the module that was injected through the trait.
     *
     * @param string $asset The asset path
     *
     * @return string The asset URL the injected module reports
     */
    public function assetUrlOf(string $asset): string
    {
        return $this->module->assetUrl($asset);
    }
}
