<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Double;

use function is_resource;
use function proc_close;
use function proc_open;
use function stream_get_contents;

use const PHP_BINARY;

/**
 * Starts one of the PHP scripts under `bin/` as a process, the way a module's language pipeline does, so a test sees
 * what that pipeline sees of it, which is the output, the error stream and the exit status. A test case composes it
 * for the stubs and the failure of its own factory.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
trait StartsBinaries
{
    /**
     * Starts a script without a shell.
     *
     * @param string                     $script      The path of the script to start
     * @param list<string>               $arguments   The arguments of the script
     * @param array<string, string>|null $environment The environment of the process, null to inherit the one of the test run
     *
     * @return array{status: int, output: string, error: string} The exit status and everything written to the two output streams
     */
    private function startScript(string $script, array $arguments, ?array $environment): array
    {
        $process = proc_open(
            [PHP_BINARY, $script, ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $environment,
        );

        if (!is_resource($process)) {
            self::fail('The binary could not be started.');
        }

        return [
            'output' => (string) stream_get_contents($pipes[1]),
            'error'  => (string) stream_get_contents($pipes[2]),
            'status' => proc_close($process),
        ];
    }
}
