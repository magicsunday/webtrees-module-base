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
use Throwable;

use function array_slice;
use function basename;
use function count;
use function dirname;
use function mb_strimwidth;
use function sort;
use function sprintf;

/**
 * Resolves the fuzzy entries of the catalogues that only differ in punctuation.
 *
 * A module runs it from its language pipeline between the merge and the compilation. It
 * counts every fuzzy entry it leaves, apart from a flagged header and obsolete entries, and
 * lists the first ones per catalogue, because a person has to judge those. It does not fail
 * for them, the commit hook of the module does.
 *
 * No line it writes starts with text taken from a catalogue or a path. Every such text sits behind a
 * fixed marker or constant words, since a CI system might read a line that starts with it as a command.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class FuzzyResolverCommand
{
    /**
     * The number of unresolved source texts listed per catalogue before the list is cut off.
     */
    private const int LISTED_PER_CATALOGUE = 10;

    /**
     * The width a listed source text is shortened to.
     */
    private const int LISTED_WIDTH = 60;

    /**
     * Resolves the catalogues named in the arguments.
     *
     * @param list<string>         $arguments The path of the messages.pot, followed by the paths of the messages.po files
     * @param Closure(string):void $write     Receives every line of the report
     *
     * @return int The exit status, 0 when all catalogues were processed, 1 when one was refused or the template is
     *             unusable, 2 when the arguments are too few
     */
    public function run(array $arguments, Closure $write): int
    {
        if (count($arguments) < 2) {
            $write('Usage: resolve-fuzzy <messages.pot> <messages.po>...');

            return 2;
        }

        $template   = $arguments[0];
        $catalogues = array_slice($arguments, 1);

        // The template is read by every catalogue, so a template that is missing or unreadable fails the whole run.
        $unreadable = CatalogueFile::unreadable($template);

        if ($unreadable !== null) {
            $write(sprintf('  ✘ %s %s', PrintableText::line($template), $unreadable));

            return 1;
        }

        $resolver  = new PunctuationResolver();
        $toolchain = new CatalogueToolchain();

        $resolvedTotal  = 0;
        $remainingTotal = 0;
        $refused        = 0;

        foreach ($catalogues as $catalogue) {
            $declined = false;
            $unusable = CatalogueFile::unusable($catalogue);

            if ($unusable !== null) {
                $write(sprintf('  ✘ %s %s', PrintableText::line($catalogue), $unusable));

                ++$refused;

                continue;
            }

            // Every layout gettext reads comes out in the canonical one, so the resolver reads a single shape. A
            // catalogue gettext cannot read is refused here, and the others still run.
            try {
                $toolchain->assertMerged($catalogue, $template);
            } catch (CatalogueNotMergedException $exception) {
                // An update leaves a file alone that the tools read to the same entries, so only an output file puts
                // it in the layout of a merge.
                $write(
                    $this->refusal(
                        $catalogue,
                        'cannot be used as it is, write it once with `msgmerge --previous --output-file=<new file> <catalogue> <template>` and replace it',
                        $exception,
                    ),
                );

                ++$refused;

                continue;
            } catch (CatalogueToolException $exception) {
                $write($this->refusal($catalogue, 'cannot be read by gettext, fix it or run msgmerge first', $exception));

                ++$refused;

                continue;
            }

            try {
                $text = $toolchain->normalise($catalogue);
            } catch (CatalogueToolException $exception) {
                $write($this->refusal($catalogue, 'cannot be read by gettext, fix it or run msgmerge first', $exception));

                ++$refused;

                continue;
            }

            try {
                $resolution = $resolver->resolve($text);

                if ($resolution->resolved > 0) {
                    // The last merge adds, drops or changes an entry when the template holds other entries than the
                    // one the catalogue was merged from. With the right template the entries stay the same, so any
                    // difference leaves the catalogue as it is.
                    $declined = !$toolchain->apply(
                        $catalogue,
                        $template,
                        $resolution,
                        fn (string $finished): bool => $this->sorted($resolver->resolve($finished)->live) === $this->sorted($resolution->live),
                    );
                }
            } catch (CatalogueLayoutException $exception) {
                $write($this->refusal($catalogue, 'holds a block that is not laid out as gettext writes it, run msgmerge first', $exception));

                ++$refused;

                continue;
            } catch (CatalogueToolException $exception) {
                $write($this->refusal($catalogue, 'could not be rewritten', $exception));

                ++$refused;

                continue;
            }

            if ($declined) {
                $write(
                    sprintf(
                        '  ✘ %s would change entries the run did not touch, merge it with this template first',
                        PrintableText::line($catalogue),
                    ),
                );

                ++$refused;

                continue;
            }

            $remaining = count($resolution->unresolved);

            $resolvedTotal  += $resolution->resolved;
            $remainingTotal += $remaining;

            if (
                ($resolution->resolved === 0)
                && ($remaining === 0)
            ) {
                continue;
            }

            $write(
                sprintf(
                    '  ▸ %-8s  resolved=%3d  remaining-fuzzy=%3d',
                    PrintableText::line(basename(dirname($catalogue))),
                    $resolution->resolved,
                    $remaining,
                ),
            );

            $this->listSources($resolution->unresolved, '- ', $write);
            $this->listSources($resolution->rewritten, '~ translation rewritten: ', $write);
        }

        $write(sprintf('  ✔ resolve-fuzzy: resolved=%d, remaining=%d', $resolvedTotal, $remainingTotal));

        return ($refused === 0) ? 0 : 1;
    }

    /**
     * Puts source texts in a fixed order, because the last merge orders the entries like the template.
     *
     * @param list<string> $sources The source texts as written in the catalogue, each with its context and a bar in
     *                              front when the entry has one
     *
     * @return list<string> The same source texts, sorted
     */
    private function sorted(array $sources): array
    {
        sort($sources);

        return $sources;
    }

    /**
     * Builds the line that names a catalogue the run refused.
     *
     * @param string    $catalogue The path of the catalogue
     * @param string    $what      What is wrong with the catalogue, as the rest of the sentence
     * @param Throwable $exception The reason, whose message is written behind the sentence
     *
     * @return string The line of the report
     */
    private function refusal(string $catalogue, string $what, Throwable $exception): string
    {
        return sprintf('  ✘ %s %s: %s', PrintableText::line($catalogue), $what, $exception->getMessage());
    }

    /**
     * Lists source texts a person has to look at, up to a limit per catalogue.
     *
     * @param list<string>         $sources The source texts as written in the catalogue, each with its context and a
     *                                      bar in front when the entry has one
     * @param string               $prefix  The marker and label in front of every listed text
     * @param Closure(string):void $write   Receives every line of the report
     */
    private function listSources(array $sources, string $prefix, Closure $write): void
    {
        foreach (array_slice($sources, 0, self::LISTED_PER_CATALOGUE) as $source) {
            $write('      ' . $prefix . mb_strimwidth(PrintableText::line($source), 0, self::LISTED_WIDTH, '…'));
        }

        $more = count($sources) - self::LISTED_PER_CATALOGUE;

        if ($more > 0) {
            $write(sprintf('      … and %d more', $more));
        }
    }
}
