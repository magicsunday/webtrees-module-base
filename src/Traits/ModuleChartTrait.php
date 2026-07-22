<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Traits;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Menu;

/**
 * Shared chart-module helpers used by the chart modules.
 *
 * Consuming classes must define a `ROUTE_DEFAULT` class constant.
 */
trait ModuleChartTrait
{
    use \Fisharebest\Webtrees\Module\ModuleChartTrait;

    public function chartBoxMenu(Individual $individual): ?Menu
    {
        return $this->chartMenu($individual);
    }

    /**
     * @param array<string, bool|int|string> $parameters
     */
    public function chartUrl(Individual $individual, array $parameters = []): string
    {
        return $this->buildRouteUrl(
            self::ROUTE_DEFAULT,
            [
                'xref' => $individual->xref(),
                'tree' => $individual->tree()->name(),
            ] + $parameters
        );
    }

    /**
     * Resolves a webtrees route to its URL. This wraps the global route() helper
     * so the URL construction above can be exercised in isolation, without a
     * booted application, by overriding this one method.
     *
     * @param string                         $routeName  The route to resolve
     * @param array<string, bool|int|string> $parameters The route parameters
     *
     * @return string
     */
    protected function buildRouteUrl(string $routeName, array $parameters): string
    {
        return route($routeName, $parameters);
    }
}
