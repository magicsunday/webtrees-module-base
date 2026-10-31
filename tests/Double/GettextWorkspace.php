<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Double;

use RuntimeException;

use function bin2hex;
use function copy;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function implode;
use function is_dir;
use function is_file;
use function is_resource;
use function json_encode;
use function mkdir;
use function proc_close;
use function proc_open;
use function random_bytes;
use function rename;
use function rmdir;
use function scandir;
use function sort;
use function stream_get_contents;
use function sys_get_temp_dir;
use function unlink;

use const JSON_THROW_ON_ERROR;

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
     * The header every plain catalogue and template of the tests starts with, followed by a blank line.
     */
    public const string HEADER = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n";

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
        foreach (['msgcat', 'msgattrib', 'msgfilter', 'msgmerge', 'msgfmt'] as $tool) {
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
        $this->store($this->catalogue, $content);
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
     * result of a merge with it. The gettext tools do the work. A catalogue they cannot read adds nothing, the
     * entries of the others are joined with the first occurrence of a source text winning, and obsolete entries,
     * fuzzy flags and previous source texts are dropped. Every translation is emptied, whatever the number of lines
     * it spans, by a filter that reads the text and writes nothing.
     *
     * @param list<string> $catalogues The paths of the catalogues to take the entries from
     *
     * @return string The text of the template
     */
    public static function templateFrom(array $catalogues): string
    {
        $readable = [];

        foreach ($catalogues as $catalogue) {
            if (self::capture(['msgcat', '--no-wrap', $catalogue]) !== null) {
                $readable[] = $catalogue;
            }
        }

        $text = self::capture(['msgcat', '--no-wrap', '--use-first', ...$readable]);
        $text = self::capture(['msgattrib', '--no-obsolete', '--clear-fuzzy', '--clear-previous', '--no-location', '--no-wrap'], $text);

        return (string) self::capture(['msgfilter', '--keep-header', '--no-wrap', 'sed', '-e', 'd'], $text);
    }

    /**
     * Writes a translations file into a directory of its own inside the scratch directory.
     *
     * @param array<array-key, array<string, string|list<string>>> $translations The translations by key and locale
     *
     * @return string The path of the file
     */
    public function writeTranslations(array $translations): string
    {
        $path = $this->emptyDirectory() . '/translations.json';

        $this->store($path, json_encode($translations, JSON_THROW_ON_ERROR));

        return $path;
    }

    /**
     * Writes the template and a catalogue merged from it, both with two entries, one of them plural, and a header
     * that declares the given number of plural forms.
     *
     * @param string $path    The path of the catalogue
     * @param int    $plurals The number of plural forms the header declares
     *
     * @return string The path of the catalogue
     */
    public function prepareMergedCatalogue(string $path, int $plurals): string
    {
        $header = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n"
            . '"Plural-Forms: nplurals=' . $plurals . "; plural=(n != 1);\\n\"\n\n";
        $forms = [];

        for ($index = 0; $index < $plurals; ++$index) {
            $forms[] = 'msgstr[' . $index . '] ""';
        }

        $entries = "msgid \"Hello\"\nmsgstr \"\"\n\nmsgid \"One\"\nmsgid_plural \"Many\"\n" . implode("\n", $forms) . "\n";

        $this->store($this->template, $header . $entries);
        $this->store($path, $header . $entries);

        self::mergeFile($path, $this->template);

        return $path;
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

        $this->store($path, $content);

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
     * Writes a file and fails when it cannot be written, so a test never runs on a scratch file that is missing.
     *
     * @param string $path    The path of the file
     * @param string $content The text to write
     */
    private function store(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('The scratch file ' . $path . ' could not be written.');
        }
    }

    /**
     * Runs a command without a shell and returns what it wrote to its output.
     *
     * @param list<string> $command The tool followed by its arguments
     * @param string|null  $input   The text to hand to the tool on its input
     *
     * @return string|null The output, or null when the command failed or could not be started
     */
    private static function capture(array $command, ?string $input = null): ?string
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            return null;
        }

        fwrite($pipes[0], $input ?? '');
        fclose($pipes[0]);

        $output = (string) stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);

        return proc_close($process) === 0 ? $output : null;
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
