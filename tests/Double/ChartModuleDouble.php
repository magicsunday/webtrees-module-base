<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Double;

use MagicSunday\Webtrees\ModuleBase\Traits\ModuleChartTrait;

/**
 * Composes the shared chart trait the way a chart module does: by supplying the
 * ROUTE_DEFAULT constant the trait resolves through late static binding.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
class ChartModuleDouble
{
    use ModuleChartTrait;

    /**
     * The route a consuming chart module registers for itself.
     */
    public const string ROUTE_DEFAULT = 'my-chart';

    /**
     * The module identifier — required by webtrees' own chart trait.
     *
     * @return string The module identifier
     */
    public function name(): string
    {
        return 'module-base-test-chart';
    }

    /**
     * The module's display title — required by webtrees' own chart trait.
     *
     * @return string The module title
     */
    public function title(): string
    {
        return 'Module base test chart';
    }
}
