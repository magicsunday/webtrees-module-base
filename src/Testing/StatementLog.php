<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Testing;

use Closure;
use Fisharebest\Webtrees\DB;

use function is_scalar;

/**
 * Collects the SQL statements that an action sends, for the tests that count the lookups of a chart.
 *
 * The log of the connection is cleared before the action and switched off afterwards, also when the
 * action throws, so the result holds exactly the statements of the action. A test that ignores some statements, such as the reads
 * of the settings, filters the list itself.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class StatementLog
{
    /**
     * Prevents instances of the helper, because it only has a static method.
     */
    private function __construct()
    {
    }

    /**
     * Returns the statements that an action sends.
     *
     * @param Closure(): void $action The action to run
     *
     * @return list<array{query: string, bindings: list<string>}> The statements in the order they were
     *                                                            sent, with their bindings as text
     */
    public static function capture(Closure $action): array
    {
        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        try {
            $action();

            $log = $connection->getQueryLog();
        } finally {
            $connection->disableQueryLog();
        }

        $statements = [];

        foreach ($log as $entry) {
            $bindings = [];

            foreach ($entry['bindings'] as $binding) {
                $bindings[] = is_scalar($binding) ? (string) $binding : '';
            }

            $statements[] = [
                'query'    => $entry['query'],
                'bindings' => $bindings,
            ];
        }

        return $statements;
    }
}
