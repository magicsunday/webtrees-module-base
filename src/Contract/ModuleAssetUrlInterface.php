<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Contract;

/**
 * Marker interface for modules that expose webtrees' asset-url helper.
 *
 * webtrees' assetUrl() lives in ModuleCustomTrait, not in any interface.
 * Module-base processors that need the helper (e.g. ImageProcessor for
 * silhouettes) cannot rely on ModuleCustomInterface alone — they must
 * be handed an object that provides assetUrl().
 *
 * Any custom module that uses ModuleCustomTrait already has the method
 * with the right signature; declaring `implements ModuleAssetUrlInterface`
 * is sufficient (the trait method satisfies the interface contract
 * without further wiring).
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
interface ModuleAssetUrlInterface
{
    /**
     * Build a URL to a module-bundled asset (image, CSS, JS, ...).
     *
     * @param string $asset Path inside the module's resources/ folder
     *
     * @return string
     */
    public function assetUrl(string $asset): string;
}
