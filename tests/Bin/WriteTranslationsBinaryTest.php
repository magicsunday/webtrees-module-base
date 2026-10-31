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
use MagicSunday\Webtrees\ModuleBase\Test\Double\StartsBinaries;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function copy;
use function file_get_contents;
use function file_put_contents;
use function json_encode;
use function mkdir;

use const JSON_THROW_ON_ERROR;

/**
 * Starts bin/write-translations as a process and pins what a module's language pipeline sees of it, which is its
 * output, its error stream and its exit status.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversNothing]
final class WriteTranslationsBinaryTest extends TestCase
{
    use StartsBinaries;

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
        $result = $this->start([]);

        self::assertSame(2, $result['status']);
        self::assertSame(
            "Usage: write-translations [--core-owned=<file.pot>] <translations.json> <messages.pot> <messages.po>...\n",
            $result['output'],
        );
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
        copy(__DIR__ . '/../../bin/write-translations', $directory . '/write-translations');

        $result = $this->startScript($directory . '/write-translations', [], null);

        self::assertSame(1, $result['status']);
        self::assertSame('', $result['output']);
        self::assertSame("  ✘ No Composer autoloader found, run composer install first.\n", $result['error']);
    }

    /**
     * A translation is written into the catalogue, the summary is printed on the output stream and the starter exits
     * without a failure.
     */
    #[Test]
    public function writesTheTranslationAndExitsWithoutAFailure(): void
    {
        $this->requireTools();

        $catalogue = $this->workspace->prepareMergedCatalogue($this->workspace->catalogue, 2);
        $file      = $this->translations(['Hello' => ['de' => 'Hallo']]);

        $result = $this->start([$file, $this->workspace->template, $catalogue]);

        self::assertSame(0, $result['status']);
        self::assertStringEndsWith("  ✔ write-translations: written=1, refused=0\n", $result['output']);
        self::assertSame('', $result['error']);
        self::assertStringContainsString('msgstr "Hallo"', (string) file_get_contents($catalogue));
    }

    /**
     * A locale of the translations file without a catalogue is named on the output stream and fails the starter, so
     * a pipeline that runs it stops.
     */
    #[Test]
    public function namesALocaleWithoutACatalogueAndFails(): void
    {
        $this->requireTools();

        $catalogue = $this->workspace->prepareMergedCatalogue($this->workspace->catalogue, 2);
        $file      = $this->translations(['Hello' => ['de' => 'Hallo', 'fr' => 'Bonjour']]);

        $result = $this->start([$file, $this->workspace->template, $catalogue]);

        self::assertSame(1, $result['status']);
        self::assertSame('', $result['error']);
        self::assertStringContainsString(
            "  ✘ the locale \"fr\" of the translations file matches no catalogue\n",
            $result['output'],
        );
        self::assertStringEndsWith("  ✔ write-translations: written=1, refused=1\n", $result['output']);
    }

    /**
     * The first argument may name the core template, and the starter hands it on, so a core template that cannot be
     * read fails the run with a line of its own.
     */
    #[Test]
    public function handsTheCoreTemplateOptionOn(): void
    {
        $this->requireTools();

        $catalogue = $this->workspace->prepareMergedCatalogue($this->workspace->catalogue, 2);
        $file      = $this->translations(['Hello' => ['de' => 'Hallo']]);
        $missing   = $this->workspace->emptyDirectory() . '/core.pot';

        $result = $this->start(['--core-owned=' . $missing, $file, $this->workspace->template, $catalogue]);

        self::assertSame(1, $result['status']);
        self::assertSame('', $result['error']);
        self::assertStringContainsString('cannot be read', $result['output']);
    }

    /**
     * Writes a translations file.
     *
     * @param array<string, array<string, string>> $translations The translations by source text and locale
     *
     * @return string The path of the file
     */
    private function translations(array $translations): string
    {
        $path = $this->workspace->emptyDirectory() . '/translations.json';

        file_put_contents($path, json_encode($translations, JSON_THROW_ON_ERROR));

        return $path;
    }

    /**
     * Skips the running test where a gettext tool is missing.
     */
    private function requireTools(): void
    {
        if (!GettextWorkspace::toolsAvailable()) {
            self::markTestSkipped('The gettext tools are not installed.');
        }
    }

    /**
     * Starts the binary of the package without a shell, in the environment of the test run.
     *
     * @param list<string> $arguments The arguments of the binary
     *
     * @return array{status: int, output: string, error: string} The exit status and everything written to the two output streams
     */
    private function start(array $arguments): array
    {
        return $this->startScript(__DIR__ . '/../../bin/write-translations', $arguments, null);
    }
}
