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
     * @param array<string, int|string> $parameters
     */
    public function chartUrl(Individual $individual, array $parameters = []): string
    {
        return route(
            self::ROUTE_DEFAULT,
            [
                'xref' => $individual->xref(),
                'tree' => $individual->tree()->name(),
            ] + $parameters
        );
    }
}
