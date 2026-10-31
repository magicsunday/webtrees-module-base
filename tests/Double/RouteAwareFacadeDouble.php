<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Double;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use MagicSunday\Webtrees\ModuleBase\Contract\ModuleAssetUrlInterface;
use MagicSunday\Webtrees\ModuleBase\Facade\RouteAwareDataFacadeTrait;

/**
 * A chart data facade as a consuming module writes it: it composes the route-aware trait and
 * reaches the trait's private members from its own methods. The two methods below stand in for
 * the module's own facade methods that build a chart URL and use the injected module.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
class RouteAwareFacadeDouble
{
    use RouteAwareDataFacadeTrait;

    /**
     * Builds the chart URL of an individual through the trait.
     *
     * @param Individual                $individual The individual the URL addresses
     * @param array<string, int|string> $parameters Extra query parameters
     *
     * @return string The chart URL
     */
    public function urlFor(Individual $individual, array $parameters = []): string
    {
        return $this->chartUrl($individual, $parameters);
    }

    /**
     * Hands out the module that was injected through the trait.
     *
     * @return ModuleCustomInterface&ModuleAssetUrlInterface The injected module
     */
    public function injectedModule(): ModuleCustomInterface&ModuleAssetUrlInterface
    {
        return $this->module;
    }
}
