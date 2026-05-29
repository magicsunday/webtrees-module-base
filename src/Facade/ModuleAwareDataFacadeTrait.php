<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Facade;

use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use MagicSunday\Webtrees\ModuleBase\Contract\ModuleAssetUrlInterface;

/**
 * Shared module-injection helper for chart DataFacade implementations that need
 * access to the owning module (for asset URLs, custom module metadata, etc.)
 * but do not need the trait's route helpers.
 */
trait ModuleAwareDataFacadeTrait
{
    private ModuleCustomInterface&ModuleAssetUrlInterface $module;

    /**
     * Sets the owning module reference used by downstream processors.
     *
     * @return static
     */
    public function setModule(ModuleCustomInterface&ModuleAssetUrlInterface $module): static
    {
        $this->module = $module;

        return $this;
    }
}
