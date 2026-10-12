<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Catalogue;

use Closure;

use function basename;
use function bin2hex;
use function chgrp;
use function chmod;
use function chown;
use function copy;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function filegroup;
use function fileowner;
use function fileperms;
use function implode;
use function is_dir;
use function is_file;
use function is_resource;
use function mkdir;
use function preg_match;
use function preg_replace_callback;
use function proc_close;
use function proc_open;
use function random_bytes;
use function rename;
use function restore_error_handler;
use function rmdir;
use function scandir;
use function set_error_handler;
use function sprintf;
use function str_starts_with;
use function strlen;
use function sys_get_temp_dir;
use function trim;
use function unlink;

/**
 * Applies an overlay of corrected entries to a catalogue with the gettext tools.
 *
 * The tools write the catalogue, so the file gets the layout that msgmerge produces. A catalogue
 * that a merge with the template would change is refused before, so the layout does not change
 * apart from the corrected entries. The overlay replaces the translation of the entries it lists and clears their fuzzy flag and
 * their previous source text. A last msgmerge against the template puts the entries back in
 * the order of the template, so the file equals what the next merge would write.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class CatalogueToolchain
{
    /**
     * Creates the toolchain. The working files go to the system temporary directory unless another one is given,
     * which a test uses to steer where they go and how they fail.
     *
     * @param string|null $temporaryDirectory The directory the working files are created in, the system one when null
     */
    public function __construct(
        private ?string $temporaryDirectory = null,
    ) {
    }

    /**
     * Rewrites a catalogue with the resolved entries of a resolution applied.
     *
     * @param string               $catalogue  The path of the messages.po to rewrite
     * @param string               $template   The path of the messages.pot the catalogue was merged from
     * @param Resolution           $resolution The overlay with the corrected entries and the selection of the entries to touch
     * @param Closure(string):bool $accept     Decides from the text of the finished catalogue whether it replaces the
     *                                         old one, and the old one stays when it says no
     *
     * @return bool True when the catalogue was replaced, false when the finished text was not accepted
     *
     * @throws CatalogueToolException When a gettext tool cannot be run or fails
     */
    public function apply(string $catalogue, string $template, Resolution $resolution, Closure $accept): bool
    {
        $catalogue = $this->unambiguous($catalogue);
        $template  = $this->unambiguous($template);

        return $this->inDirectory(
            function (string $directory) use ($catalogue, $template, $resolution, $accept): bool {
                $log   = $directory . '/tool.log';
                $files = [
                    'overlay'   => $directory . '/overlay.po',
                    'selection' => $directory . '/selection.po',
                    'step'      => $directory . '/step.po',
                    'cleared'   => $directory . '/cleared.po',
                    'others'    => $directory . '/others.po',
                    'joined'    => $directory . '/joined.po',
                ];

                foreach (['overlay' => $resolution->overlay, 'selection' => $resolution->selection] as $name => $content) {
                    $path = $files[$name];

                    // A cut file would be read by the tools as a shorter, valid one and leave the old translation.
                    if ($this->silently(static fn (): int|false => file_put_contents($path, $content)) !== strlen($content)) {
                        throw new CatalogueToolException(sprintf('Could not write the %s of the entries to correct.', $name));
                    }
                }

                $this->run(
                    $log,
                    ['msgcat', '--use-first', $files['overlay'], $catalogue, '--output-file=' . $files['step']],
                );
                $this->run(
                    $log,
                    [
                        'msgattrib',
                        '--only-file=' . $files['selection'],
                        '--clear-fuzzy',
                        '--clear-previous',
                        '--output-file=' . $files['cleared'],
                        $files['step'],
                    ],
                );
                $this->run(
                    $log,
                    [
                        'msgattrib',
                        '--ignore-file=' . $files['selection'],
                        '--output-file=' . $files['others'],
                        $files['step'],
                    ],
                );
                $this->run(
                    $log,
                    ['msgcat', '--use-first', $files['cleared'], $files['others'], '--output-file=' . $files['joined']],
                );
                $this->run(
                    $log,
                    ['msgmerge', '--quiet', '--previous', '--update', '--backup=none', $files['joined'], $template],
                );

                if (!$accept($this->canonical($directory, $files['joined']))) {
                    return false;
                }

                $this->replace($catalogue, $files['joined']);

                return true;
            },
        );
    }

    /**
     * Checks that a catalogue is what a merge with the template writes, byte for byte.
     *
     * The tools read the catalogue and write it again. A catalogue that a merge leaves as it is can be read without
     * loss, because anything the tools would cut, join or rewrite shows as a difference. One written by another
     * tool, edited by hand, laid out with other options or merged from another template is not, so the caller has
     * to merge it first.
     *
     * @param string $catalogue The path of the messages.po to check
     * @param string $template  The path of the template the catalogue was merged from
     *
     * @throws CatalogueNotMergedException When the merge changes the catalogue
     * @throws CatalogueToolException      When a tool cannot be run
     */
    public function assertMerged(string $catalogue, string $template): void
    {
        $catalogue = $this->unambiguous($catalogue);
        $template  = $this->unambiguous($template);

        $this->inDirectory(
            function (string $directory) use ($catalogue, $template): bool {
                $merged = $directory . '/merged.po';

                // An output file is written whenever the merge result holds an entry, whereas an update leaves the
                // file alone when the merge changes nothing the tools understood, which is the case for text they
                // cut or join silently.
                $this->run(
                    $directory . '/tool.log',
                    ['msgmerge', '--quiet', '--previous', '--output-file=' . $merged, $catalogue, $template],
                );

                // A merge result without an entry is not written at all, so a catalogue that holds no entry beyond the
                // header, merged from a template of the same kind, has nothing to compare and is as it should be.
                if (
                    !is_file($merged)
                    && ($this->canonical($directory, $catalogue) === '')
                ) {
                    return true;
                }

                $original  = $this->silently(static fn (): string|false => file_get_contents($catalogue));
                $rewritten = $this->silently(static fn (): string|false => file_get_contents($merged));

                if (
                    ($original === false)
                    || ($rewritten === false)
                ) {
                    throw new CatalogueNotMergedException(
                        sprintf('%s is not what a merge with this template writes.', PrintableText::line($catalogue)),
                    );
                }

                // An update keeps the date of the template in the header of a catalogue whose entries did not change,
                // whereas the output file takes the date of the template, so that one header line is not compared.
                $creation = '/^"POT-Creation-Date: [^\n]*\\\\n"$/m';

                if (preg_match($creation, $original, $line) === 1) {
                    $rewritten = (string) preg_replace_callback(
                        $creation,
                        static fn (): string => $line[0],
                        $rewritten,
                        1,
                    );
                }

                if ($original !== $rewritten) {
                    throw new CatalogueNotMergedException(
                        sprintf('%s is not what a merge with this template writes.', PrintableText::line($catalogue)),
                    );
                }

                return true;
            },
        );
    }

    /**
     * Reads a catalogue in the canonical layout of gettext.
     *
     * Every layout gettext reads comes out the same: the flags of an entry on one line, one keyword per line without
     * indentation, a literal on one line, and one blank line between the entries. What reads the text afterwards
     * has to know that one layout only.
     *
     * @param string $catalogue The path of the messages.po to read
     *
     * @return string The text of the catalogue in the canonical layout
     *
     * @throws CatalogueToolException When gettext cannot read the catalogue or a tool cannot be run
     */
    public function normalise(string $catalogue): string
    {
        $catalogue = $this->unambiguous($catalogue);

        return $this->inDirectory(fn (string $directory): string => $this->canonical($directory, $catalogue));
    }

    /**
     * Writes a catalogue in the canonical layout and returns its text.
     *
     * @param string $directory The working directory
     * @param string $source    The path of the catalogue to read
     *
     * @return string The text of the catalogue in the canonical layout
     *
     * @throws CatalogueToolException When gettext cannot read the catalogue or the result cannot be read
     */
    private function canonical(string $directory, string $source): string
    {
        $target = $directory . '/canonical-' . bin2hex(random_bytes(4)) . '.po';

        $this->run($directory . '/tool.log', ['msgcat', '--no-wrap', '--output-file=' . $target, $source]);

        // The tool writes no file for a catalogue without any entry.
        if (!is_file($target)) {
            return '';
        }

        $content = $this->silently(static fn (): string|false => file_get_contents($target));

        if ($content === false) {
            throw new CatalogueToolException('Could not read the catalogue in the canonical layout.');
        }

        return $content;
    }

    /**
     * Runs an operation in a working directory that is removed afterwards.
     *
     * @template T
     *
     * @param Closure(string): T $work The operation, which receives the path of the directory
     *
     * @return T The result of the operation
     *
     * @throws CatalogueToolException When the directory cannot be created
     */
    private function inDirectory(Closure $work)
    {
        $root      = $this->temporaryDirectory ?? sys_get_temp_dir();
        $directory = $root . '/resolve-fuzzy-' . bin2hex(random_bytes(6));

        if (!$this->silently(static fn (): bool => mkdir($directory, 0700))) {
            throw new CatalogueToolException(sprintf('Could not create the working directory %s.', PrintableText::line($directory)));
        }

        try {
            return $work($directory);
        } finally {
            $this->silently(
                static function () use ($directory): bool {
                    $left = scandir($directory);

                    foreach (($left === false ? [] : $left) as $name) {
                        if (is_file($directory . '/' . $name)) {
                            unlink($directory . '/' . $name);
                        }
                    }

                    return is_dir($directory) && rmdir($directory);
                },
            );
        }
    }

    /**
     * Replaces a file with the content of another, so a reader never sees a half-written catalogue.
     *
     * @param string $target The file to replace
     * @param string $source The file that holds the new content
     *
     * @throws CatalogueToolException When the new content cannot be written
     */
    private function replace(string $target, string $source): void
    {
        // The name is random, so a link a contributor committed under a name this code would have
        // chosen cannot make the copy write through it.
        $staging = dirname($target) . '/.' . basename($target) . '.' . bin2hex(random_bytes(6)) . '.resolving';

        $mode  = $this->silently(static fn (): int|false => fileperms($target));
        $owner = $this->silently(static fn (): int|false => fileowner($target));
        $group = $this->silently(static fn (): int|false => filegroup($target));

        $written = $this->silently(
            static function () use ($source, $staging, $target, $mode, $owner, $group): bool {
                if (
                    !copy($source, $staging)
                    || (($mode !== false) && !chmod($staging, $mode & 0777))
                ) {
                    return false;
                }

                // The copy belongs to the process that runs this, which is root in a container, so the owner and the
                // group of the catalogue are put back. Only root can hand a file to another owner, and a user can hand
                // it to a group it belongs to. Where that is not allowed the copy keeps the owner of the process, as
                // it always did, so the result of both calls is not checked.
                if ($owner !== false) {
                    chown($staging, $owner);
                }

                if ($group !== false) {
                    chgrp($staging, $group);
                }

                return rename($staging, $target);
            }
        );

        if (!$written) {
            $this->silently(static fn (): bool => is_file($staging) && unlink($staging));

            throw new CatalogueToolException(sprintf('Could not write %s.', PrintableText::line($target)));
        }
    }

    /**
     * Makes a relative path safe to hand to a tool as an argument.
     *
     * A path that starts with a dash would be read as an option, and a directory of a catalogue can
     * be named like that.
     *
     * @param string $path The path as the caller gave it
     *
     * @return string The path, prefixed with the current directory when it starts with a dash
     */
    private function unambiguous(string $path): string
    {
        return str_starts_with($path, '-') ? './' . $path : $path;
    }

    /**
     * Runs an operation without the PHP warning it raises when it fails.
     *
     * The warning names the paths involved, or a tool that is not installed. The caller reports
     * the failure on its own, with the control characters of a path replaced.
     *
     * @template T
     *
     * @param Closure(): T $operation The operation, whose failure shows in its result
     *
     * @return T The result of the operation
     */
    private function silently(Closure $operation)
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Runs one gettext tool without a shell.
     *
     * @param string       $log     The file that receives what the tool writes to its output and error stream
     * @param list<string> $command The tool followed by its arguments
     *
     * @throws CatalogueToolException When the tool cannot be started or exits with a failure
     */
    private function run(string $log, array $command): void
    {
        // Both streams are appended to one file that starts empty, so a tool that writes a lot to either
        // cannot fill a pipe that nobody reads and block, and neither stream overwrites the other.
        $this->silently(static fn (): int|false => file_put_contents($log, ''));

        $pipes   = [];
        $process = $this->silently(
            static fn () => proc_open($command, [1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes),
        );

        if (!is_resource($process)) {
            throw new CatalogueToolException(sprintf('Could not start %s, are the gettext tools installed?', $command[0]));
        }

        $status = proc_close($process);

        if ($status !== 0) {
            throw new CatalogueToolException(
                sprintf(
                    '%s failed with exit status %d. %s',
                    PrintableText::line(implode(' ', $command)),
                    $status,
                    PrintableText::line(trim((string) $this->silently(static fn (): string|false => file_get_contents($log)))),
                ),
            );
        }
    }
}
