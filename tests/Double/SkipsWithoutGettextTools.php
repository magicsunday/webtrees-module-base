<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Double;

/**
 * Skips a test where one of the gettext tools is missing. A test case composes it for every test that runs the
 * tools, so a run without them reports those tests as skipped.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
trait SkipsWithoutGettextTools
{
    /**
     * Skips the running test where a gettext tool is missing.
     */
    private function requireTools(): void
    {
        if (!GettextWorkspace::toolsAvailable()) {
            self::markTestSkipped('The gettext tools are not installed.');
        }
    }
}
