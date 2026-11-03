<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Double;

use MagicSunday\Webtrees\ModuleBase\View\SharedViews;

use function view;

/**
 * Renders a shared view through the webtrees view registry, so a test can query the markup with
 * the markup fixture of the library.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class ViewFixture
{
    /**
     * Parameters of a plain render of the detail panel view.
     *
     * @var array<string, string>
     */
    public const array DETAIL_PANEL_PARAMETERS = [
        'id'              => 'webtrees-fan-detail-panel',
        'moduleClass'     => 'webtrees-fan-detail-panel',
        'closeLabel'      => 'close',
        'hint'            => 'Select a person',
        'emptyText'       => 'No details',
        'individualLabel' => 'Open page',
        'centerLabel'     => 'Center chart',
    ];

    /**
     * Static-only helper, never instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Renders a shared view with the namespace registered.
     *
     * @param array<string, string|list<array{key: string, text: string, mode?: string}>|null> $data The view parameters
     *
     * @return string The rendered markup
     */
    public static function render(string $name, array $data): string
    {
        SharedViews::register();

        return view(SharedViews::NAMESPACE . '::' . $name, $data);
    }
}
