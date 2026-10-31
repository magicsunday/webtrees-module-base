<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Double;

use Closure;

/**
 * Builds the writer that a catalogue command test hands to the command, so the test sees the lines of the report.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
trait CollectsReportLines
{
    /**
     * Builds a writer that collects the lines of the report.
     *
     * @param list<string> $lines The list the lines are appended to
     *
     * @return Closure(string):void The writer to hand to the command
     */
    private function collect(array &$lines): Closure
    {
        return static function (string $line) use (&$lines): void {
            $lines[] = $line;
        };
    }
}
