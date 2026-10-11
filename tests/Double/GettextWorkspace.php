<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Double;

use function bin2hex;
use function copy;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_dir;
use function is_file;
use function is_resource;
use function mkdir;
use function preg_match;
use function proc_close;
use function proc_open;
use function random_bytes;
use function rename;
use function rmdir;
use function scandir;
use function sort;
use function str_starts_with;
use function stream_get_contents;
use function sys_get_temp_dir;
use function unlink;

/**
 * A scratch copy of the fuzzy fixture catalogue, and the gettext tools to check it with.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class GettextWorkspace
{
    /**
     * The path of the messages.pot copy.
     */
    public string $template;

    /**
     * The path of the messages.po copy, in a directory named after its locale.
     */
    public string $catalogue;

    /**
     * The directory that holds the copies.
     */
    private string $directory;

    /**
     * Copies the fixture catalogue into a fresh scratch directory.
     */
    public function __construct()
    {
        $this->directory = sys_get_temp_dir() . '/fuzzy-workspace-' . bin2hex(random_bytes(6));

        mkdir($this->directory . '/de', 0700, true);

        $this->template  = $this->directory . '/messages.pot';
        $this->catalogue = $this->directory . '/de/messages.po';

        copy(__DIR__ . '/../fixtures/fuzzy/template.pot', $this->template);
        copy(__DIR__ . '/../fixtures/fuzzy/de/messages.po', $this->catalogue);
    }

    /**
     * Tells whether the gettext tools the resolver and these checks need can be started.
     *
     * @return bool True when every tool answers
     */
    public static function toolsAvailable(): bool
    {
        foreach (['msgcat', 'msgattrib', 'msgmerge', 'msgfmt'] as $tool) {
            if (self::execute([$tool, '--version']) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reads the catalogue copy.
     *
     * @return string The text of the catalogue
     */
    public function read(): string
    {
        return (string) file_get_contents($this->catalogue);
    }

    /**
     * Replaces the text of the catalogue copy.
     *
     * @param string $content The new text of the catalogue
     */
    public function write(string $content): void
    {
        file_put_contents($this->catalogue, $content);
    }

    /**
     * Merges the catalogue copy from the template the way the language pipeline of a module does.
     *
     * @return int The exit status of the merge
     */
    public function merge(): int
    {
        return self::execute(
            ['msgmerge', '--quiet', '--previous', '--update', '--backup=none', $this->catalogue, $this->template],
        );
    }

    /**
     * Merges any catalogue from a template the way the language pipeline of a module does, so it is what a merge
     * with that template writes.
     *
     * @param string $catalogue The path of the catalogue to merge in place
     * @param string $template  The path of the template
     *
     * @return int The exit status of the merge
     */
    public static function mergeFile(string $catalogue, string $template): int
    {
        $target = $catalogue . '.merged';
        $status = self::execute(['msgmerge', '--quiet', '--previous', '--output-file=' . $target, $catalogue, $template]);

        if ($status === 0) {
            rename($target, $catalogue);
        }

        return $status;
    }

    /**
     * Builds the text of a template that holds exactly the entries of the given catalogues, so each of them is the
     * result of a merge with it.
     *
     * @param list<string> $catalogues The paths of the catalogues to take the entries from
     *
     * @return string The text of the template
     */
    public static function templateFrom(array $catalogues): string
    {
        $blocks = [];

        foreach ($catalogues as $catalogue) {
            $target = sys_get_temp_dir() . '/template-from-' . bin2hex(random_bytes(6)) . '.po';

            if (self::execute(['msgcat', '--no-wrap', '--output-file=' . $target, $catalogue]) !== 0) {
                continue;
            }

            $text = is_file($target) ? (string) file_get_contents($target) : '';

            if (is_file($target)) {
                unlink($target);
            }

            foreach (explode("\n\n", $text) as $block) {
                $lines  = [];
                $plural = false;

                foreach (explode("\n", $block) as $line) {
                    if (str_starts_with($line, '#~')) {
                        continue 2;
                    }

                    if (preg_match('/^(msgctxt|msgid|msgid_plural) /', $line) === 1) {
                        $lines[] = $line;
                        $plural  = $plural || str_starts_with($line, 'msgid_plural');
                    }
                }

                if (
                    ($lines === [])
                    || ($lines === ['msgid ""'])
                ) {
                    continue;
                }

                $lines[] = $plural ? "msgstr[0] \"\"\nmsgstr[1] \"\"" : 'msgstr ""';

                $blocks[implode("\n", $lines)] = implode("\n", $lines);
            }
        }

        return "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n" . implode("\n\n", $blocks) . "\n";
    }

    /**
     * Checks the catalogue copy with msgfmt.
     *
     * @return int The exit status of the check
     */
    public function check(): int
    {
        return self::execute(['msgfmt', '--check', '--output-file=/dev/null', $this->catalogue]);
    }

    /**
     * Adds a catalogue for another locale to the scratch directory.
     *
     * @param string $locale  The locale, which names the directory of the catalogue
     * @param string $content The text of the catalogue
     *
     * @return string The path of the new messages.po
     */
    public function addCatalogue(string $locale, string $content): string
    {
        mkdir($this->directory . '/' . $locale, 0700, true);

        $path = $this->directory . '/' . $locale . '/messages.po';

        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Creates an empty directory inside the scratch directory.
     *
     * @return string The path of the directory
     */
    public function emptyDirectory(): string
    {
        $path = $this->directory . '/scratch';

        mkdir($path, 0700, true);

        return $path;
    }

    /**
     * Lists the files that are left in the directory of the catalogue copy.
     *
     * @return list<string> The file names, sorted
     */
    public function files(): array
    {
        $names = [];

        $found = scandir($this->directory . '/de');

        foreach (($found === false ? [] : $found) as $name) {
            if (is_file($this->directory . '/de/' . $name)) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Removes the scratch directory.
     */
    public function remove(): void
    {
        self::removeTree($this->directory);
    }

    /**
     * Removes a directory with everything below it.
     *
     * @param string $path The directory to remove
     */
    private static function removeTree(string $path): void
    {
        $names = scandir($path);

        foreach (($names === false ? [] : $names) as $name) {
            if (
                ($name === '.')
                || ($name === '..')
            ) {
                continue;
            }

            if (is_dir($path . '/' . $name)) {
                self::removeTree($path . '/' . $name);

                continue;
            }

            unlink($path . '/' . $name);
        }

        rmdir($path);
    }

    /**
     * Runs a command without a shell and returns its exit status.
     *
     * @param list<string> $command The tool followed by its arguments
     *
     * @return int The exit status, or -1 when the command could not be started
     */
    private static function execute(array $command): int
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            return -1;
        }

        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);

        return proc_close($process);
    }
}
