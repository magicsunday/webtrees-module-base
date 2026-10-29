<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\View;

use Fisharebest\Webtrees\View;

use function dirname;

/**
 * Makes the views of this package available to a chart module.
 *
 * A module calls {@see self::register()} from its boot() and then renders a view as
 * `view(SharedViews::NAMESPACE . '::detail-panel', [...])`.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class SharedViews
{
    /**
     * The webtrees view namespace under which the views are registered.
     */
    public const string NAMESPACE = 'webtrees-module-base';

    /**
     * Registers the view namespace, so the views of this package can be rendered. Calling it
     * more than once is harmless.
     */
    public static function register(): void
    {
        View::registerNamespace(self::NAMESPACE, dirname(__DIR__, 2) . '/resources/views/');
    }

    /**
     * Static-only helper, never instantiated.
     */
    private function __construct()
    {
    }
}
