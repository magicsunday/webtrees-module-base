<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Catalogue;

use Closure;
use MagicSunday\Webtrees\ModuleBase\Catalogue\CatalogueBlockParser;
use MagicSunday\Webtrees\ModuleBase\Catalogue\CatalogueFile;
use MagicSunday\Webtrees\ModuleBase\Catalogue\CatalogueLayoutException;
use MagicSunday\Webtrees\ModuleBase\Catalogue\CatalogueToolchain;
use MagicSunday\Webtrees\ModuleBase\Catalogue\CatalogueToolException;
use MagicSunday\Webtrees\ModuleBase\Catalogue\FuzzyResolverCommand;
use MagicSunday\Webtrees\ModuleBase\Catalogue\ParsedEntry;
use MagicSunday\Webtrees\ModuleBase\Catalogue\PrintableText;
use MagicSunday\Webtrees\ModuleBase\Catalogue\PunctuationResolver;
use MagicSunday\Webtrees\ModuleBase\Catalogue\Resolution;
use MagicSunday\Webtrees\ModuleBase\Catalogue\ResolvedEntry;
use MagicSunday\Webtrees\ModuleBase\Test\Double\GettextWorkspace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_map;
use function array_pop;
use function array_slice;
use function array_values;
use function chmod;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_file;
use function is_link;
use function is_readable;
use function is_writable;
use function preg_match;
use function range;
use function rename;
use function sprintf;
use function str_contains;
use function str_repeat;
use function str_replace;
use function symlink;

/**
 * Pins the report of the command, its exit status and that a catalogue without anything to
 * resolve is never rewritten.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(FuzzyResolverCommand::class)]
#[UsesClass(PunctuationResolver::class)]
#[UsesClass(CatalogueBlockParser::class)]
#[UsesClass(CatalogueFile::class)]
#[UsesClass(CatalogueLayoutException::class)]
#[UsesClass(ParsedEntry::class)]
#[UsesClass(ResolvedEntry::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(PrintableText::class)]
#[UsesClass(CatalogueToolchain::class)]
final class FuzzyResolverCommandTest extends TestCase
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
     * Fewer than two arguments print the usage and return the usage status.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function explainsTheUsageWhenACatalogueIsMissing(): void
    {
        $lines = [];

        $status = $this->runCommand([$this->workspace->template], $this->collect($lines));

        self::assertSame(2, $status);
        self::assertSame(['Usage: resolve-fuzzy <messages.pot> <messages.po>...'], $lines);
    }

    /**
     * A catalogue that does not exist is named and the run fails.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function failsForAFileThatDoesNotExist(): void
    {
        $lines = [];

        $status = $this->runCommand(
            [$this->workspace->template, $this->workspace->catalogue . '.missing'],
            $this->collect($lines),
        );

        self::assertSame(1, $status);
        self::assertSame(['  ✘ ' . $this->workspace->catalogue . '.missing not found', '  ✔ resolve-fuzzy: resolved=0, remaining=0'], $lines);
    }

    /**
     * A catalogue with only unresolvable entries is reported and its file is left untouched.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function reportsWithoutRewritingWhenNothingCanBeResolved(): void
    {
        $this->requireTools();

        $this->workspace->write(
            "msgid \"\"\nmsgstr \"\"\n\"Language: de\\n\"\n\n"
            . "#, fuzzy\n#| msgid \"Deaths per decade\"\nmsgid \"Deaths per century\"\nmsgstr \"Tode pro Jahrzehnt\"\n",
        );

        file_put_contents($this->workspace->template, GettextWorkspace::templateFrom([$this->workspace->catalogue]));
        GettextWorkspace::mergeFile($this->workspace->catalogue, $this->workspace->template);

        $before = $this->workspace->read();
        $lines  = [];

        $status = (new FuzzyResolverCommand())->run(
            [$this->workspace->template, $this->workspace->catalogue],
            $this->collect($lines),
        );

        self::assertSame(0, $status);
        self::assertSame($before, $this->workspace->read());
        self::assertSame(
            [
                '  ▸ de        resolved=  0  remaining-fuzzy=  1',
                '      - Deaths per century',
                '  ✔ resolve-fuzzy: resolved=0, remaining=1',
            ],
            $lines,
        );
    }

    /**
     * A catalogue without fuzzy entries adds no line to the report.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function staysSilentAboutACatalogueWithoutFuzzyEntries(): void
    {
        $this->requireTools();

        $this->workspace->write("msgid \"\"\nmsgstr \"\"\n\n#: src/A.php:1\nmsgid \"A\"\nmsgstr \"B\"\n");

        $lines = [];

        $this->runCommand([$this->workspace->template, $this->workspace->catalogue], $this->collect($lines));

        self::assertSame(['  ✔ resolve-fuzzy: resolved=0, remaining=0'], $lines);
    }

    /**
     * Only the first source texts are listed, longer ones are shortened and the rest is counted.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function listsAtMostTenSourceTextsPerCatalogueAndShortensLongOnes(): void
    {
        $this->requireTools();

        $this->workspace->write($this->unresolvedCatalogue(12, str_repeat('x', 80)));

        $lines = [];

        $this->runCommand([$this->workspace->template, $this->workspace->catalogue], self::collect($lines));

        self::assertSame('  ▸ de        resolved=  0  remaining-fuzzy= 12', $lines[0]);
        self::assertSame('      - ' . str_repeat('x', 59) . '…', $lines[1]);
        self::assertSame('      - Text 10', $lines[10]);
        self::assertSame('      … and 2 more', $lines[11]);
        self::assertSame('  ✔ resolve-fuzzy: resolved=0, remaining=12', $lines[12]);
    }

    /**
     * Exactly as many source texts as the limit are all listed and no more-line follows.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function addsNoMoreLineForExactlyTenSourceTexts(): void
    {
        $this->requireTools();

        $this->workspace->write($this->unresolvedCatalogue(10, 'Text 1'));

        $lines = [];

        $this->runCommand([$this->workspace->template, $this->workspace->catalogue], self::collect($lines));

        self::assertSame(
            [
                '  ▸ de        resolved=  0  remaining-fuzzy= 10',
                '      - Text 1',
                '      - Text 2',
                '      - Text 3',
                '      - Text 4',
                '      - Text 5',
                '      - Text 6',
                '      - Text 7',
                '      - Text 8',
                '      - Text 9',
                '      - Text 10',
                '  ✔ resolve-fuzzy: resolved=0, remaining=10',
            ],
            $lines,
        );
    }

    /**
     * Each catalogue with something to report gets its lines, a quiet one in between changes nothing and the totals add up.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function reportsEveryCatalogueAndSumsThemUp(): void
    {
        $this->requireTools();

        $quiet = $this->workspace->addCatalogue('aa', "msgid \"\"\nmsgstr \"\"\n\n#: src/A.php:1\nmsgid \"A\"\nmsgstr \"B\"\n");

        $this->workspace->write($this->unresolvedCatalogue(1, 'Text 1'));

        $other = $this->workspace->addCatalogue('fr', $this->unresolvedCatalogue(2, 'Text 1'));
        $lines = [];

        $this->runCommand(
            [$this->workspace->template, $quiet, $this->workspace->catalogue, $other],
            self::collect($lines),
        );

        self::assertSame(
            [
                '  ▸ de        resolved=  0  remaining-fuzzy=  2',
                '      - Text 1',
                '      - Text 2',
                '  ▸ fr        resolved=  0  remaining-fuzzy=  2',
                '      - Text 1',
                '      - Text 2',
                '  ✔ resolve-fuzzy: resolved=0, remaining=4',
            ],
            $lines,
        );
    }

    /**
     * A missing catalogue after a valid one is named, and the valid one is still processed.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function namesACatalogueThatDoesNotExistAndGoesOn(): void
    {
        $this->requireTools();

        $lines = [];

        $status = $this->runCommand(
            [$this->workspace->template, $this->workspace->catalogue, $this->workspace->catalogue . '.missing'],
            self::collect($lines),
        );

        self::assertSame(1, $status);
        self::assertContains('  ✘ ' . $this->workspace->catalogue . '.missing not found', $lines);

        $summary = array_pop($lines);

        self::assertIsString($summary);
        self::assertMatchesRegularExpression('/^  ✔ resolve-fuzzy: resolved=[1-9]/', $summary);
    }

    /**
     * A missing catalogue in front of a valid one is named, and the valid one is still processed.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function processesTheCataloguesAfterAMissingOne(): void
    {
        $this->requireTools();

        $lines = [];

        $status = $this->runCommand(
            [$this->workspace->template, $this->workspace->catalogue . '.missing', $this->workspace->catalogue],
            self::collect($lines),
        );

        self::assertSame(1, $status);
        self::assertContains('  ✘ ' . $this->workspace->catalogue . '.missing not found', $lines);

        $summary = array_pop($lines);

        self::assertIsString($summary);
        self::assertMatchesRegularExpression('/^  ✔ resolve-fuzzy: resolved=[1-9]/', $summary);
    }

    /**
     * The template is only read as the template and never searched for fuzzy entries.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function doesNotTreatTheTemplateAsACatalogue(): void
    {
        $this->requireTools();

        file_put_contents($this->workspace->template, self::unresolvedCatalogue(1, 'Text 1'));

        $quiet = $this->workspace->addCatalogue('aa', "msgid \"\"\nmsgstr \"\"\n");
        $lines = [];

        GettextWorkspace::mergeFile($quiet, $this->workspace->template);

        (new FuzzyResolverCommand())->run([$this->workspace->template, $quiet], self::collect($lines));

        // The fuzzy entry of the template reaches the catalogue by the merge and is counted once, for the catalogue.
        self::assertSame(
            [
                '  ▸ aa        resolved=  0  remaining-fuzzy=  1',
                '      - Text 1',
                '  ✔ resolve-fuzzy: resolved=0, remaining=1',
            ],
            $lines,
        );
    }

    /**
     * A catalogue the user cannot read is reported as not readable instead of counting as empty.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function failsForACatalogueThatIsNotReadable(): void
    {
        chmod($this->workspace->catalogue, 0000);

        try {
            if (is_readable($this->workspace->catalogue)) {
                self::markTestSkipped('The file stays readable for this user.');
            }

            $lines = [];

            $status = $this->runCommand(
                [$this->workspace->template, $this->workspace->catalogue],
                self::collect($lines),
            );

            self::assertSame(1, $status);
            self::assertSame(['  ✘ ' . $this->workspace->catalogue . ' is not readable', '  ✔ resolve-fuzzy: resolved=0, remaining=0'], $lines);
        } finally {
            chmod($this->workspace->catalogue, 0600);
        }
    }

    /**
     * A template the user cannot read is reported as not readable and the run fails before any catalogue is processed.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function failsForATemplateThatIsNotReadable(): void
    {
        chmod($this->workspace->template, 0000);

        try {
            if (is_readable($this->workspace->template)) {
                self::markTestSkipped('The file stays readable for this user.');
            }

            $lines = [];

            $status = $this->runCommand(
                [$this->workspace->template, $this->workspace->catalogue],
                self::collect($lines),
            );

            self::assertSame(1, $status);
            self::assertSame(['  ✘ ' . $this->workspace->template . ' is not readable'], $lines);
        } finally {
            chmod($this->workspace->template, 0600);
        }
    }

    /**
     * A catalogue that is a link is refused, because the new file would replace the link and leave the file it
     * points to as it was while the report named the entries resolved.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function refusesACatalogueThatIsALink(): void
    {
        $target = $this->workspace->catalogue . '.target';

        rename($this->workspace->catalogue, $target);
        symlink($target, $this->workspace->catalogue);

        $before = file_get_contents($target);
        $lines  = [];

        $status = $this->runCommand(
            [$this->workspace->template, $this->workspace->catalogue],
            self::collect($lines),
        );

        self::assertSame(1, $status);
        self::assertSame(['  ✘ ' . $this->workspace->catalogue . ' is a link, name the file it points to', '  ✔ resolve-fuzzy: resolved=0, remaining=0'], $lines);
        self::assertTrue(is_link($this->workspace->catalogue), 'The link stays a link.');
        self::assertSame($before, file_get_contents($target));
    }

    /**
     * A catalogue in a layout that gettext reads but msgmerge never writes is read in the canonical layout, so its
     * entries are resolved like those of any other.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function resolvesACatalogueInALayoutMsgmergeDoesNotWrite(): void
    {
        $this->requireTools();

        $content = (string) file_get_contents($this->workspace->catalogue);

        $this->workspace->write(
            str_replace(
                ["#, fuzzy\n#| msgid \"Births", "msgid \"Births · per year\"\nmsgstr"],
                ["#, fuzzy php-format\n#| msgid \"Births", " msgid   \"Births · per year\"\n\tmsgstr"],
                $content,
            ),
        );

        $lines = [];

        $status = $this->runCommand(
            [$this->workspace->template, $this->workspace->catalogue],
            self::collect($lines),
        );

        self::assertSame(0, $status);
        self::assertStringContainsString(
            "msgid \"Births · per year\"\nmsgstr \"Geburten · pro Jahr\"",
            $this->workspace->read(),
        );
    }

    /**
     * A catalogue that gettext cannot read is refused with a line of its own, left as it is, and the others are
     * still processed.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function refusesACatalogueGettextCannotRead(): void
    {
        $this->requireTools();

        $this->workspace->write(
            "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgstr \"orphan\"\n",
        );

        $lines = $this->runRefused();

        self::assertStringStartsWith('  ✘ ', $lines[0]);
        self::assertStringContainsString('cannot be read by gettext, fix it or run msgmerge first', $lines[0]);
        self::assertSame('  ✔ resolve-fuzzy: resolved=0, remaining=0', $lines[1]);
    }

    /**
     * A catalogue that is refused does not stop the catalogues after it.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function processesTheCataloguesAfterARefusedOne(): void
    {
        $this->requireTools();

        $refused = $this->workspace->addCatalogue(
            'aa',
            "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgstr \"orphan\"\n",
        );

        $lines = [];

        $status = $this->runCommand(
            [$this->workspace->template, $refused, $this->workspace->catalogue],
            self::collect($lines),
        );

        self::assertSame(1, $status);
        $summary = array_pop($lines);

        self::assertIsString($summary);
        self::assertMatchesRegularExpression('/^  ✔ resolve-fuzzy: resolved=[1-9]/', $summary);
    }

    /**
     * A catalogue that cannot be rewritten is named on a line of its own and does not stop the catalogues after it.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function processesTheCataloguesAfterOneThatCannotBeRewritten(): void
    {
        $this->requireTools();

        $locked = $this->workspace->addCatalogue('aa', (string) file_get_contents($this->workspace->catalogue));

        file_put_contents($this->workspace->template, GettextWorkspace::templateFrom([$locked, $this->workspace->catalogue]));
        GettextWorkspace::mergeFile($locked, $this->workspace->template);

        chmod(dirname($locked), 0500);

        try {
            if (is_writable(dirname($locked))) {
                self::markTestSkipped('The directory stays writable for this user.');
            }

            $lines = [];

            $status = $this->runCommand(
                [$this->workspace->template, $locked, $this->workspace->catalogue],
                self::collect($lines),
            );

            self::assertSame(1, $status);
            self::assertMatchesRegularExpression('/✘ .*aa\/messages\.po could not be rewritten: /', implode("\n", $lines));

            $summary = array_pop($lines);

            self::assertIsString($summary);
            self::assertMatchesRegularExpression('/^  ✔ resolve-fuzzy: resolved=[1-9]/', $summary);
        } finally {
            chmod(dirname($locked), 0700);
        }
    }

    /**
     * A catalogue whose charset label does not match its bytes would lose a translation in the last merge, so the
     * run names it and leaves it as it was.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function leavesACatalogueAloneWhoseCharsetLabelDoesNotMatchItsBytes(): void
    {
        $this->requireTools();

        file_put_contents(
            $this->workspace->template,
            "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n"
            . "msgid \"A – B\"\nmsgstr \"\"\n\nmsgid \"Plain\"\nmsgstr \"\"\n",
        );

        $this->workspace->write(
            "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=ISO-8859-1\\n\"\n\n"
            . "#, fuzzy\n#| msgid \"A - B\"\nmsgid \"A – B\"\nmsgstr \"X - Y\"\n\nmsgid \"Plain\"\nmsgstr \"x\"\n",
        );

        $before = $this->workspace->read();
        $lines  = $this->runRefused();

        self::assertMatchesRegularExpression(
            '/✘ .*messages\\.po is not what a merge with this template writes/',
            implode("\n", $lines),
        );
        self::assertSame($before, $this->workspace->read());
    }

    /**
     * A catalogue that gettext reads but that holds a block the resolver does not accept, here an entry to resolve
     * without a header, is named on a line of its own and left as it is, while the others still run.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function refusesACatalogueTheResolverDoesNotAcceptAndGoesOn(): void
    {
        $this->requireTools();

        $content = "#, fuzzy\n#| msgid \"Born  died\"\nmsgid \"Born died\"\nmsgstr \"Geboren gestorben\"\n";
        $refused = $this->workspace->addCatalogue('aa', $content);
        $lines   = [];

        $status = (new FuzzyResolverCommand())->run(
            [$this->workspace->template, $refused, $this->workspace->catalogue],
            self::collect($lines),
        );

        self::assertSame(1, $status);
        self::assertMatchesRegularExpression(
            '/✘ .*aa\\/messages\\.po is not what a merge with this template writes/',
            implode("\n", $lines),
        );
        self::assertSame($content, (string) file_get_contents($refused));

        $summary = array_pop($lines);

        self::assertIsString($summary);
        self::assertMatchesRegularExpression('/^  ✔ resolve-fuzzy: resolved=[1-9]/', $summary);
    }

    /**
     * A template whose flags differ from the ones of the catalogue makes the last merge mark an entry fuzzy that was
     * not, so the run names the catalogue and leaves it as it was.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function leavesACatalogueAloneWhoseEntryTheLastMergeWouldMarkFuzzy(): void
    {
        $this->requireTools();

        $header = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n";

        file_put_contents(
            $this->workspace->template,
            $header . "msgid \"A · B\"\nmsgstr \"\"\n\nmsgid \"Count %d\"\nmsgstr \"\"\n",
        );

        $this->workspace->write(
            $header . "#, fuzzy\n#| msgid \"A — B\"\nmsgid \"A · B\"\nmsgstr \"X — Y\"\n\n"
            . "#, range: 1..5\nmsgid \"Count %d\"\nmsgstr \"Anzahl %d\"\n",
        );

        $before = $this->workspace->read();
        $lines  = $this->runRefused();

        self::assertMatchesRegularExpression(
            '/✘ .*messages\\.po is not what a merge with this template writes/',
            implode("\n", $lines),
        );
        self::assertSame($before, $this->workspace->read());
    }

    /**
     * Characters of a source text that set the direction of a text do not reach the report.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function writesNoControlCharacterOfACatalogueIntoTheReport(): void
    {
        $this->requireTools();

        $this->workspace->write(
            "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n#, fuzzy\n#| msgid \"Old\"\nmsgid \"A \u{202E}X::error::y\"\nmsgstr \"Alt\"\n",
        );

        $lines = [];

        $this->runCommand([$this->workspace->template, $this->workspace->catalogue], self::collect($lines));

        self::assertSame('      - A ?X::error::y', $lines[1]);
        self::assertSame(0, preg_match('/[\x00-\x08\x0B-\x1F\x7F]/', implode("\n", $lines)));
    }

    /**
     * A line feed in a path does not start a line of its own in the report.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function keepsALineFeedOfAPathOutOfTheReport(): void
    {
        $lines = [];

        $status = $this->runCommand(
            [$this->workspace->template, $this->workspace->catalogue . '
::error::x'],
            self::collect($lines),
        );

        self::assertSame(1, $status);
        self::assertSame(['  ✘ ' . $this->workspace->catalogue . '?::error::x not found', '  ✔ resolve-fuzzy: resolved=0, remaining=0'], $lines);
    }

    /**
     * The name of the directory of a catalogue reaches the report on one line.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function keepsALineFeedOfALocaleNameOutOfTheReport(): void
    {
        $this->requireTools();

        $other = $this->workspace->addCatalogue('x
::error::y', $this->unresolvedCatalogue(1, 'Text 1'));
        $lines = [];

        $this->runCommand([$this->workspace->template, $other], self::collect($lines));

        self::assertSame('  ▸ x?::error::y  resolved=  0  remaining-fuzzy=  1', $lines[0]);
    }

    /**
     * A locale name that looks like a CI command is never the first thing of a line of the report.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function startsEveryLineOfTheReportWithAMarker(): void
    {
        $other = $this->workspace->addCatalogue('::error::x##[warning]y', $this->unresolvedCatalogue(1, 'Text 1'));
        $lines = [];

        $this->runCommand([$this->workspace->template, $other], self::collect($lines));

        self::assertNotSame([], $lines);

        foreach ($lines as $line) {
            self::assertStringNotContainsString('##[', $line);
            self::assertSame(0, preg_match('/^\s*::/', $line), $line);
            self::assertSame(1, preg_match('/^\s*(▸|-|~|…|✔|✘)/u', $line), $line);
        }
    }

    /**
     * Provides a template that is not the one the catalogue was merged from, as the line it holds in place of one of
     * the catalogue.
     *
     * @return array<string, array{string, string}> The rows, keyed by the difference, each with the line of the
     *                                              template and the one of the catalogue that it replaces
     */
    public static function templatesOfAnotherMerge(): array
    {
        return [
            'source text changed again'      => ['msgid "Births - per year"', 'msgid "Births · per year"'],
            'source text changed completely' => ['msgid "Births · per annum"', 'msgid "Births · per year"'],
            'live entry missing from it'     => ['msgid "Completely different text"', 'msgid "Stable entry"'],
            'plural text changed'            => ['msgid_plural "%s children total"', 'msgid_plural "%s children · total"'],
            'context changed'                => ['msgctxt "months"', 'msgctxt "month"'],
        ];
    }

    /**
     * A template other than the one of the merge would change entries the run did not touch, which is reported.
     *
     * @param string $inTemplate The line the template holds
     * @param string $replaced   The line of the catalogue that it replaces
     *
     * @throws CatalogueToolException
     */
    #[Test]
    #[DataProvider('templatesOfAnotherMerge')]
    public function reportsATemplateOfAnotherMerge(string $inTemplate, string $replaced): void
    {
        $this->requireTools();

        file_put_contents(
            $this->workspace->template,
            str_replace(
                $replaced,
                $inTemplate,
                (string) file_get_contents($this->workspace->template),
            ),
        );

        $lines = $this->runRefused();

        self::assertMatchesRegularExpression(
            '/✘ .*messages\\.po is not what a merge with this template writes/',
            implode("\n", $lines),
        );
        self::assertStringContainsString('resolved=0,', implode("\n", $lines));
    }

    /**
     * A rewritten translation is listed with the source text behind a marker, whatever the source text starts with.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function listsARewrittenTranslationBehindAMarker(): void
    {
        $this->requireTools();

        $other = $this->workspace->addCatalogue(
            'it',
            "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n#, fuzzy\n#| msgid \"::error::x - y\"\nmsgid \"::error::x · y\"\nmsgstr \"Foo - Bar\"\n",
        );
        $lines = [];

        $this->runCommand([$this->templateOf(['::error::x · y']), $other], self::collect($lines));

        self::assertSame(
            ['  ▸ it        resolved=  1  remaining-fuzzy=  0', '      ~ translation rewritten: ::error::x · y'],
            array_slice($lines, 0, 2),
        );

        foreach ($lines as $line) {
            self::assertSame(1, preg_match('/^\s*(▸|-|~|…|✔|✘)/u', $line), $line);
        }
    }

    /**
     * A catalogue whose entries stand in another order than the ones of the template is processed like one in the
     * order of the template.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function resolvesACatalogueWhoseEntriesAreInAnotherOrderThanTheTemplate(): void
    {
        $this->requireTools();

        $other = $this->workspace->addCatalogue(
            'it',
            "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n"
            . "msgid \"Gamma\"\nmsgstr \"c\"\n\n"
            . "#, fuzzy\n#| msgid \"Beta - x\"\nmsgid \"Beta · x\"\nmsgstr \"B - x\"\n\n"
            . "msgid \"Alpha\"\nmsgstr \"a\"\n",
        );
        $lines = [];

        $status = $this->runCommand(
            [$this->templateOf(['Alpha', 'Beta · x', 'Gamma']), $other],
            self::collect($lines),
        );

        self::assertSame(0, $status);
        self::assertSame('  ▸ it        resolved=  1  remaining-fuzzy=  0', $lines[0]);
    }

    /**
     * Rewritten translations are listed up to the limit per catalogue, and the rest is counted.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function listsAtMostTenRewrittenTranslationsPerCatalogue(): void
    {
        $this->requireTools();

        $entries = [];

        for ($number = 1; $number <= 11; ++$number) {
            $entries[] = sprintf(
                "#, fuzzy\n#| msgid \"Entry %d - x\"\nmsgid \"Entry %d · x\"\nmsgstr \"Eintrag %d - x\"",
                $number,
                $number,
                $number,
            );
        }

        $other = $this->workspace->addCatalogue(
            'es',
            "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n" . implode("\n\n", $entries) . "\n",
        );
        $lines = [];

        $this->runCommand([$this->templateOf(array_map(static fn (int $number): string => sprintf('Entry %d · x', $number), range(1, 11))), $other], self::collect($lines));

        self::assertSame('  ▸ es        resolved= 11  remaining-fuzzy=  0', $lines[0]);
        $rewritten = array_values(array_filter($lines, static fn (string $line): bool => str_contains($line, '~ translation rewritten:')));

        self::assertCount(10, $rewritten, 'The report lists no more rewritten translations per catalogue than the cap allows.');
        self::assertSame('      ~ translation rewritten: Entry 1 · x', $rewritten[0]);
        self::assertSame('      ~ translation rewritten: Entry 10 · x', $rewritten[9]);
        self::assertContains('      … and 1 more', $lines);
    }

    /**
     * A template that does not exist is named and the run fails.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function failsForATemplateThatDoesNotExist(): void
    {
        $lines = [];

        $status = $this->runCommand(
            [$this->workspace->template . '.missing', $this->workspace->catalogue],
            self::collect($lines),
        );

        self::assertSame(1, $status);
        self::assertSame(['  ✘ ' . $this->workspace->template . '.missing not found'], $lines);
    }

    /**
     * Two catalogues are resolved, the totals add up, a further merge changes nothing and a second run is a no-op.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function resolvesTheCataloguesAndDoesNothingOnTheSecondRun(): void
    {
        $this->requireTools();

        $other = $this->workspace->addCatalogue('fr', $this->workspace->read());
        $lines = [];

        $first = $this->runCommand(
            [$this->workspace->template, $this->workspace->catalogue, $other],
            self::collect($lines),
        );

        self::assertSame(0, $first);
        self::assertSame(
            [
                '  ▸ de        resolved=  4  remaining-fuzzy=  2',
                '      - Deaths per century',
                '      - %s child · total',
                '      ~ translation rewritten: Births · per year',
                '      ~ translation rewritten: A very long source text that is wrapped over several lines …',
                '      ~ translation rewritten: May · short',
                '      ~ translation rewritten: Say \\"hi\\" · path C:\\\\x',
                '  ▸ fr        resolved=  4  remaining-fuzzy=  2',
                '      - Deaths per century',
                '      - %s child · total',
                '      ~ translation rewritten: Births · per year',
                '      ~ translation rewritten: A very long source text that is wrapped over several lines …',
                '      ~ translation rewritten: May · short',
                '      ~ translation rewritten: Say \\"hi\\" · path C:\\\\x',
                '  ✔ resolve-fuzzy: resolved=8, remaining=4',
            ],
            $lines,
        );

        $written = $this->workspace->read();

        self::assertStringContainsString("msgstr \"Geburten · pro Jahr\"\n", $written);
        self::assertSame(0, $this->workspace->merge());
        self::assertSame($written, $this->workspace->read());

        $lines = [];

        $this->runCommand(
            [$this->workspace->template, $this->workspace->catalogue, $other],
            self::collect($lines),
        );

        self::assertSame($written, $this->workspace->read());
        self::assertSame('  ✔ resolve-fuzzy: resolved=0, remaining=4', array_pop($lines));
    }

    /**
     * Builds a catalogue that holds fuzzy entries no punctuation change explains.
     *
     * @param int    $count The number of entries
     * @param string $first The source text of the first entry, the others are called "Text" and their number
     *
     * @return string The text of the catalogue
     */
    private function unresolvedCatalogue(int $count, string $first): string
    {
        $entries = [];

        for ($number = 1; $number <= $count; ++$number) {
            $text      = $number === 1 ? $first : 'Text ' . $number;
            $entries[] = sprintf("#, fuzzy\n#| msgid \"Old %d\"\nmsgid \"%s\"\nmsgstr \"Alt\"", $number, $text);
        }

        return "msgid \"\"\nmsgstr \"\"\n\n" . implode("\n\n", $entries) . "\n";
    }

    /**
     * Skips the running test where a gettext tool is missing, because the command reads every catalogue with one.
     */
    private function requireTools(): void
    {
        if (!GettextWorkspace::toolsAvailable()) {
            self::markTestSkipped('The gettext tools are not installed.');
        }
    }

    /**
     * Runs the command after the catalogues went through the merge the language pipeline of a module runs first.
     *
     * @param list<string> $arguments The template and the catalogues
     * @param Closure      $write     The writer of the report
     *
     * @return int The status of the command
     */
    private function runCommand(array $arguments, Closure $write): int
    {
        $template = $arguments[0] ?? '';

        if ($template === $this->workspace->template) {
            $catalogues = array_values(array_filter(array_slice($arguments, 1), static fn (string $path): bool => is_file($path) && is_readable($path)));

            file_put_contents($template, GettextWorkspace::templateFrom($catalogues));
        }

        if (is_file($template)) {
            foreach (array_slice($arguments, 1) as $catalogue) {
                if (
                    is_file($catalogue)
                    && !is_link($catalogue)
                    && is_readable($catalogue)
                    && is_writable(dirname($catalogue))
                ) {
                    GettextWorkspace::mergeFile($catalogue, $template);
                }
            }
        }

        return (new FuzzyResolverCommand())->run($arguments, $write);
    }

    /**
     * Runs the command on the catalogue of the workspace and asserts that it is refused and left as it was.
     *
     * @return list<string> The lines of the report
     *
     * @throws CatalogueToolException
     */
    private function runRefused(): array
    {
        $before = $this->workspace->read();
        $lines  = [];

        $status = (new FuzzyResolverCommand())->run(
            [$this->workspace->template, $this->workspace->catalogue],
            self::collect($lines),
        );

        self::assertSame(1, $status);
        self::assertSame($before, $this->workspace->read());

        return $lines;
    }

    /**
     * Writes a template that holds exactly the given source texts, so a catalogue made of them is the merge of it.
     *
     * @param list<string> $sources The source texts, as written in the catalogue
     *
     * @return string The path of the template
     */
    private function templateOf(array $sources): string
    {
        $entries = [];

        foreach ($sources as $source) {
            $entries[] = sprintf("msgid \"%s\"\nmsgstr \"\"", $source);
        }

        $path = $this->workspace->emptyDirectory() . '/messages.pot';

        file_put_contents(
            $path,
            "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n" . implode("\n\n", $entries) . "\n",
        );

        return $path;
    }

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
