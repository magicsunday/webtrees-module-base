<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Bin;

use MagicSunday\Webtrees\ModuleBase\Test\Double\GettextWorkspace;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chmod;
use function copy;
use function count;
use function file_put_contents;
use function is_resource;
use function mkdir;
use function preg_match;
use function proc_close;
use function proc_open;
use function shell_exec;
use function stream_get_contents;
use function trim;

use const PHP_BINARY;

/**
 * Starts bin/resolve-fuzzy as a process and pins what a module's language pipeline sees of
 * it, which is its output and its exit status.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversNothing]
final class ResolveFuzzyBinaryTest extends TestCase
{
    /**
     * The scratch copy of the fixture catalogue that the running test works on.
     */
    private GettextWorkspace $workspace;

    /**
     * Creates the scratch copy before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = new GettextWorkspace();
    }

    /**
     * Removes the scratch copy after each test.
     */
    protected function tearDown(): void
    {
        $this->workspace->remove();

        parent::tearDown();
    }

    /**
     * Without arguments the starter prints the usage and exits with the usage status.
     */
    #[Test]
    public function printsTheUsageWithoutArguments(): void
    {
        $result = $this->start([], null);

        self::assertSame(2, $result['status']);
        self::assertSame("Usage: resolve-fuzzy <messages.pot> <messages.po>...\n", $result['output']);
        self::assertSame('', $result['error']);
    }

    /**
     * Without a Composer autoloader the starter says so on the error stream and fails.
     */
    #[Test]
    public function reportsAMissingAutoloaderAndFails(): void
    {
        $directory = $this->workspace->emptyDirectory() . '/package/bin';

        mkdir($directory, 0700, true);
        copy(__DIR__ . '/../../bin/resolve-fuzzy', $directory . '/resolve-fuzzy');

        $result = $this->start([], null, $directory . '/resolve-fuzzy');

        self::assertSame(1, $result['status']);
        self::assertSame('', $result['output']);
        self::assertSame("  ✘ No Composer autoloader found, run composer install first.\n", $result['error']);
    }

    /**
     * A tool that cannot be started is named with the catalogue it was needed for and the starter fails.
     */
    #[Test]
    public function namesACatalogueWhoseToolCannotBeStarted(): void
    {
        $result = $this->start(
            [$this->workspace->template, $this->workspace->catalogue],
            ['PATH' => $this->toolDirectory([])],
        );

        self::assertSame(1, $result['status']);
        self::assertSame('', $result['error']);
        self::assertStringContainsString(
            'could not be rewritten: Could not start msgattrib, are the gettext tools installed?',
            $result['output'],
        );
    }

    /**
     * The failure message is a single line that starts with the marker, so output of a tool cannot start
     * a line of its own that a CI system reads as a command.
     */
    #[Test]
    public function writesAFailureMessageAsOneLineWithTheMarker(): void
    {
        $result = $this->start(
            [$this->workspace->template, $this->workspace->catalogue],
            ['PATH' => $this->toolDirectory(['msgattrib' => "printf 'first\\n::error::second\\n'\nexit 3"])],
        );

        self::assertSame(1, $result['status']);
        self::assertSame('', $result['error']);
        self::assertSame(1, preg_match('/^  ✘ [^\n]*failed with exit status 3\. first\?::error::second\n  ✔ [^\n]*\n$/u', $result['output']));
    }

    /**
     * A catalogue that gettext cannot read is named on the output and the starter fails.
     */
    #[Test]
    public function namesACatalogueGettextCannotRead(): void
    {
        if (!GettextWorkspace::toolsAvailable()) {
            self::markTestSkipped('The gettext tools are not installed.');
        }

        $this->workspace->write("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgstr \"orphan\"\n");

        $result = $this->start([$this->workspace->template, $this->workspace->catalogue], null);

        self::assertSame(1, $result['status']);
        self::assertSame('', $result['error']);
        self::assertStringContainsString('cannot be read by gettext, fix it or run msgmerge first', $result['output']);
    }

    /**
     * A catalogue is resolved, the summary is printed and the starter exits without a failure.
     */
    #[Test]
    public function resolvesACatalogueAndExitsWithoutAFailure(): void
    {
        if (!GettextWorkspace::toolsAvailable()) {
            self::markTestSkipped('The gettext tools are not installed.');
        }

        $result = $this->start([$this->workspace->template, $this->workspace->catalogue], null);

        self::assertSame(0, $result['status']);
        self::assertStringEndsWith("  ✔ resolve-fuzzy: resolved=4, remaining=2\n", $result['output']);
        self::assertSame('', $result['error']);
    }

    /**
     * Makes a directory of tools for the PATH of the binary. Reading a catalogue goes through the real msgcat, and
     * every other tool is the given stub or missing.
     *
     * @param array<string, string> $stubs The body of a shell script per tool name
     *
     * @return string The path of the directory
     */
    private function toolDirectory(array $stubs): string
    {
        if (!GettextWorkspace::toolsAvailable()) {
            self::markTestSkipped('The gettext tools are not installed.');
        }

        $directory = $this->workspace->emptyDirectory() . '/tools-' . count($stubs);

        mkdir($directory, 0700, true);

        $stubs['msgcat'] = 'exec ' . trim((string) shell_exec('command -v msgcat')) . ' "$@"';

        $stubs += ['msgmerge' => 'exec ' . trim((string) shell_exec('command -v msgmerge')) . ' "$@"'];

        foreach ($stubs as $name => $body) {
            file_put_contents($directory . '/' . $name, "#!/bin/sh\n" . $body . "\n");
            chmod($directory . '/' . $name, 0700);
        }

        return $directory;
    }

    /**
     * Starts the binary without a shell.
     *
     * @param list<string>               $arguments   The arguments of the binary
     * @param array<string, string>|null $environment The environment of the process, null to inherit the one of the test run
     * @param string                     $script      The path of the script to start, the one of the package by default
     *
     * @return array{status: int, output: string, error: string} The exit status and everything written to the two output streams
     */
    private function start(array $arguments, ?array $environment, string $script = __DIR__ . '/../../bin/resolve-fuzzy'): array
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
