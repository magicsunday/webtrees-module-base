<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Catalogue;

use MagicSunday\Webtrees\ModuleBase\Catalogue\CatalogueLayoutException;
use MagicSunday\Webtrees\ModuleBase\Catalogue\CatalogueNotMergedException;
use MagicSunday\Webtrees\ModuleBase\Catalogue\CatalogueToolchain;
use MagicSunday\Webtrees\ModuleBase\Catalogue\CatalogueToolException;
use MagicSunday\Webtrees\ModuleBase\Catalogue\ParsedEntry;
use MagicSunday\Webtrees\ModuleBase\Catalogue\PrintableText;
use MagicSunday\Webtrees\ModuleBase\Catalogue\PunctuationResolver;
use MagicSunday\Webtrees\ModuleBase\Catalogue\Resolution;
use MagicSunday\Webtrees\ModuleBase\Catalogue\ResolvedEntry;
use MagicSunday\Webtrees\ModuleBase\Test\Double\GettextWorkspace;
use MagicSunday\Webtrees\ModuleBase\Test\Double\ShortWriteStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_diff;
use function array_values;
use function chdir;
use function chmod;
use function clearstatcache;
use function copy;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function getcwd;
use function getenv;
use function is_link;
use function is_writable;
use function mkdir;
use function preg_match_all;
use function putenv;
use function restore_error_handler;
use function scandir;
use function set_error_handler;
use function str_replace;
use function stream_wrapper_register;
use function stream_wrapper_unregister;
use function symlink;

/**
 * Runs the overlay of the resolver through the real gettext tools on a copy of the fixture
 * catalogue. The tests that run a real tool skip themselves where the tools are not installed.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(CatalogueToolchain::class)]
#[UsesClass(PrintableText::class)]
#[UsesClass(PunctuationResolver::class)]
#[UsesClass(ParsedEntry::class)]
#[UsesClass(ResolvedEntry::class)]
#[UsesClass(Resolution::class)]
final class CatalogueToolchainTest extends TestCase
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
     * The corrected translations are written and the other fuzzy entries stay fuzzy.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function writesTheResolvedTranslationsAndKeepsTheOthersFuzzy(): void
    {
        $this->requireTools();

        $this->apply();

        $content = $this->workspace->read();

        self::assertStringContainsString(
            "#: src/A.php:10\nmsgid \"Births · per year\"\nmsgstr \"Geburten · pro Jahr\"",
            $content,
        );
        self::assertStringContainsString("msgctxt \"month\"\nmsgid \"May · short\"\nmsgstr \"Mai · kurz\"", $content);
        self::assertStringContainsString('umbrechen, weil er die Zeilenbreite überschreitet · mit einem Trenner"', $content);
        self::assertStringContainsString('msgstr "Sag \\"hi\\" · Pfad C:\\\\x"', $content);
        self::assertStringContainsString(
            "#, fuzzy\n#| msgid \"Deaths per decade\"\nmsgid \"Deaths per century\"",
            $content,
        );
        self::assertStringContainsString("#, fuzzy, php-format\n#| msgid \"%s child — total\"", $content);
        self::assertSame(2, preg_match_all('/^#, .*fuzzy/m', $content));
    }

    /**
     * Every layout gettext reads is written in the one canonical layout, so what follows reads a single shape.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function normalisesEveryLayoutGettextReadsToTheCanonicalOne(): void
    {
        $this->requireTools();

        $header = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Language: de\\n\"\n";

        $this->workspace->write(
            $header . "\n"
            . "# note\n#, fuzzy php-format\n#| msgid \"A - B\"\n msgid   \"A — B\"\n\tmsgstr\t\"X - Y\"\n\n\n"
            . "#, fuzzy\n#| msgid \"Long \"\n#| \"text - here\"\nmsgid \"\"\n\"Long \"\n\"text — here\"\nmsgstr \"\"\n\"Lang \"\n\"text - hier\"\n\n"
            . "# apart\n\nmsgid \"Plain\"\nmsgstr \"Einfach\"\n",
        );

        self::assertSame(
            $header . "\n"
            . "# note\n#, fuzzy, php-format\n#| msgid \"A - B\"\nmsgid \"A — B\"\nmsgstr \"X - Y\"\n\n"
            . "#, fuzzy\n#| msgid \"Long text - here\"\nmsgid \"Long text — here\"\nmsgstr \"Lang text - hier\"\n\n"
            . "# apart\nmsgid \"Plain\"\nmsgstr \"Einfach\"\n",
            (new CatalogueToolchain())->normalise($this->workspace->catalogue),
        );
    }

    /**
     * A catalogue that is already canonical is returned as it is.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function returnsACanonicalCatalogueUnchanged(): void
    {
        $this->requireTools();

        $toolchain = new CatalogueToolchain();
        $canonical = $toolchain->normalise($this->workspace->catalogue);

        $this->workspace->write($canonical);

        self::assertSame($canonical, $toolchain->normalise($this->workspace->catalogue));
    }

    /**
     * A catalogue gettext cannot read is reported by the tool instead of being read by guessing.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function failsForACatalogueGettextCannotRead(): void
    {
        $this->requireTools();

        $this->workspace->write("msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgstr \"orphan\"\n");

        $this->expectException(CatalogueToolException::class);

        (new CatalogueToolchain())->normalise($this->workspace->catalogue);
    }

    /**
     * A catalogue without any entry is returned as the empty text instead of raising an error.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function returnsTheEmptyTextForACatalogueWithoutEntries(): void
    {
        $this->requireTools();

        $this->workspace->write("msgid \"\"\nmsgstr \"\"\n");

        self::assertSame('', (new CatalogueToolchain())->normalise($this->workspace->catalogue));
    }

    /**
     * Provides the entries of a catalogue that a merge would change, keyed by what makes it so. Each is text the
     * tools would cut, join or rewrite when they read and write it.
     *
     * @return array<string, array{string}> The rows, each with the entries after the header
     */
    public static function catalogueTextAMergeWouldChange(): array
    {
        return [
            'line ending of a carriage return alone'  => ["# note\r#, fuzzy\n#| msgid \"A - B\"\nmsgid \"A · B\"\nmsgstr \"a - b\"\n"],
            'windows line endings'                    => ["msgid \"A\"\r\nmsgstr \"a\"\r\n"],
            'carriage returns only'                   => ["msgid \"A\"\rmsgstr \"a\"\r"],
            'escape of NUL in octal'                  => ["msgid \"Keep\"\nmsgstr \"keep\\000 tail\"\n"],
            'escape of NUL in hex'                    => ["msgid \"Keep\"\nmsgstr \"keep\\x00 tail\"\n"],
            'escape of NUL as short octal'            => ["msgid \"Keep\"\nmsgstr \"keep\\0 tail\"\n"],
            'escape of NUL as octal beyond a byte'    => ["msgid \"Keep\"\nmsgstr \"ab\\400cd\"\n"],
            'escape of NUL as hex beyond a byte'      => ["msgid \"Keep\"\nmsgstr \"ab\\x100 cd\"\n"],
            'escape of NUL as long hex'               => ["msgid \"Keep\"\nmsgstr \"ab\\x0100 cd\"\n"],
            'raw NUL byte'                            => ["msgid \"Keep\"\nmsgstr \"abc\0def\"\n"],
            'numeric escape that is no NUL'           => ["msgid \"Keep\"\nmsgstr \"a\\x5b\\101c\"\n"],
            'escaped backslash and a continued digit' => ["msgid \"Keep\"\nmsgstr \"Beh\\\\\n0alten\"\n"],
            'backslash at the end of a line'          => ["msgid \"Keep\"\nmsgstr \"Beh\\\n alten\"\n"],
            'numeric escape in a comment'             => ["#: src\\0\\file.php:3\nmsgid \"Keep\"\nmsgstr \"keep\"\n"],
            'numeric escape in an indented comment'   => ["  # note \\x00 here\nmsgid \"Keep\"\nmsgstr \"keep\"\n"],
            'NUL escape in a previous text'           => ["#| msgid \"Old\\0\"\nmsgid \"Keep\"\nmsgstr \"keep\"\n"],
            'NUL escape in an obsolete entry'         => ["#~ msgid \"Old\"\n#~ msgstr \"a\\x00 b\"\n\nmsgid \"Keep\"\nmsgstr \"keep\"\n"],
        ];
    }

    /**
     * A catalogue that a merge with the template would change is refused, so nothing of it is cut, joined or
     * rewritten unseen by a tool that reads and writes it.
     *
     * @param string $entries The entries after the header
     *
     * @throws CatalogueNotMergedException
     * @throws CatalogueToolException
     */
    #[Test]
    #[DataProvider('catalogueTextAMergeWouldChange')]
    public function refusesACatalogueThatAMergeWouldChange(string $entries): void
    {
        $this->requireTools();

        $this->workspace->write(
            "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n" . $entries,
        );

        // The template holds the entries of the catalogue, so only the text the tools read differently is left as a
        // reason for the refusal.
        file_put_contents($this->workspace->template, GettextWorkspace::templateFrom([$this->workspace->catalogue]));

        $this->expectException(CatalogueNotMergedException::class);
        $this->expectExceptionMessageMatches('/is not what a merge with this template writes/');

        (new CatalogueToolchain())->assertMerged($this->workspace->catalogue, $this->workspace->template);
    }

    /**
     * A catalogue that a merge with its template leaves as it is passes the check.
     *
     * @throws CatalogueNotMergedException
     * @throws CatalogueToolException
     */
    #[Test]
    public function acceptsACatalogueThatAMergeLeavesAsItIs(): void
    {
        $this->requireTools();

        $this->expectNotToPerformAssertions();

        (new CatalogueToolchain())->assertMerged($this->workspace->catalogue, $this->workspace->template);
    }

    /**
     * A catalogue that cannot be read raises the exception of the toolchain instead of a PHP warning.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function failsForACatalogueThatCannotBeRead(): void
    {
        $this->expectException(CatalogueToolException::class);
        $this->expectExceptionMessageMatches('/msgcat/');

        (new CatalogueToolchain())->normalise($this->workspace->catalogue . '.missing');
    }

    /**
     * A fuzzy flag on the header is a mark of its own and is not cleared by resolving an entry.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function keepsTheFuzzyFlagOfTheHeader(): void
    {
        $this->requireTools();

        $this->workspace->write(
            "#, fuzzy\nmsgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Language: de\\n\"\n\n"
            . "#, fuzzy\n#| msgid \"Births — per year\"\nmsgid \"Births · per year\"\nmsgstr \"Geburten — pro Jahr\"\n",
        );

        $this->apply();

        $content = $this->workspace->read();

        self::assertStringStartsWith("#, fuzzy\nmsgid \"\"\nmsgstr \"\"\n", $content);
        self::assertStringContainsString("msgid \"Births · per year\"\nmsgstr \"Geburten · pro Jahr\"", $content);
    }

    /**
     * Obsolete entries, a fuzzy one with its previous text among them, come through a run unchanged.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function keepsTheObsoleteEntries(): void
    {
        $this->requireTools();

        $this->workspace->write(
            "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n"
            . "#, fuzzy\n#| msgid \"Births — per year\"\nmsgid \"Births · per year\"\nmsgstr \"Geburten — pro Jahr\"\n\n"
            . "#~ msgid \"Qqq xjw zzk\"\n#~ msgstr \"Eins\"\n\n"
            . "#, fuzzy\n#~| msgid \"Older\"\n#~ msgid \"Vvv kzp qqx\"\n#~ msgstr \"Zwei\"\n",
        );

        $this->apply();

        $content = $this->workspace->read();

        self::assertStringContainsString("msgid \"Births · per year\"\nmsgstr \"Geburten · pro Jahr\"", $content);
        self::assertStringContainsString("#~ msgid \"Qqq xjw zzk\"\n#~ msgstr \"Eins\"", $content);
        self::assertStringContainsString(
            "#, fuzzy\n#~| msgid \"Older\"\n#~ msgid \"Vvv kzp qqx\"\n#~ msgstr \"Zwei\"",
            $content,
        );
    }

    /**
     * A block of comments in front of the header does not bring the header into the selection, so its flag stays.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function keepsTheFuzzyFlagOfAHeaderBehindACommentBlock(): void
    {
        $this->requireTools();

        $this->workspace->write(
            "# License block\n\n#, fuzzy\nmsgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Language: de\\n\"\n\n"
            . "#, fuzzy\n#| msgid \"Births — per year\"\nmsgid \"Births · per year\"\nmsgstr \"Geburten — pro Jahr\"\n",
        );

        $this->apply();

        $content = $this->workspace->read();

        self::assertStringStartsWith('# License block', $content);
        self::assertStringContainsString("#, fuzzy\nmsgid \"\"\nmsgstr \"\"\n", $content);
        self::assertStringContainsString("msgid \"Births · per year\"\nmsgstr \"Geburten · pro Jahr\"", $content);
    }

    /**
     * A header whose first translation line starts right after the keyword does not come into the selection.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function keepsTheFuzzyFlagOfAHeaderThatStartsItsTextOnTheKeywordLine(): void
    {
        $this->requireTools();

        $this->workspace->write(
            "#, fuzzy\nmsgid \"\"\nmsgstr \"Language: de\\n\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n"
            . "#, fuzzy\n#| msgid \"Births — per year\"\nmsgid \"Births · per year\"\nmsgstr \"Geburten — pro Jahr\"\n",
        );

        $this->apply();

        $content = $this->workspace->read();

        self::assertStringContainsString("#, fuzzy\nmsgid \"\"\nmsgstr \"\"\n", $content);
        self::assertStringContainsString("msgid \"Births · per year\"\nmsgstr \"Geburten · pro Jahr\"", $content);
    }

    /**
     * A header that is laid out in a way gettext accepts but the tools do not write is kept out of the selection too.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function keepsTheFuzzyFlagOfAHeaderWithSpaceAroundItsKeywords(): void
    {
        $this->requireTools();

        $this->workspace->write(
            "#, fuzzy\nmsgid  \"\" \nmsgstr\t\"\"\n  \"Language: de\\n\" \n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n"
            . "#, fuzzy\n#| msgid \"Births — per year\"\nmsgid \"Births · per year\"\nmsgstr \"Geburten — pro Jahr\"\n",
        );

        $this->apply();

        $content = $this->workspace->read();

        self::assertStringContainsString("#, fuzzy\nmsgid \"\"\nmsgstr \"\"\n", $content);
        self::assertStringContainsString("msgid \"Births · per year\"\nmsgstr \"Geburten · pro Jahr\"", $content);
    }

    /**
     * The file mode of the catalogue is the one it had before, not the default of a new file.
     *
     * @throws CatalogueLayoutException
     * @throws CatalogueToolException
     */
    #[Test]
    public function keepsTheFileModeOfTheCatalogue(): void
    {
        $this->requireTools();

        chmod($this->workspace->catalogue, 0640);

        $this->apply();

        clearstatcache();

        self::assertSame(0640, fileperms($this->workspace->catalogue) & 0777);
    }

    /**
     * An overlay without a header still clears the flag of its first entry, also when that entry wraps its source text.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function clearsTheFlagOfTheFirstEntryOfAnOverlayWithoutHeader(): void
    {
        $this->requireTools();

        $this->workspace->write(
            "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n"
            . "#, fuzzy\n#| msgid \"Stable  entry\"\nmsgid \"Stable entry\"\nmsgstr \"Stabiler Eintrag\"\n",
        );

        (new CatalogueToolchain())->apply(
            $this->workspace->catalogue,
            $this->workspace->template,
            new Resolution(
                "msgid \"\"\n\"Stable entry\"\nmsgstr \"Stabiler Eintrag\"\n",
                "msgid \"\"\n\"Stable entry\"\nmsgstr \"Stabiler Eintrag\"\n",
                1,
                [],
                [],
                [],
            ),
            static fn (): bool => true,
        );

        $content = $this->workspace->read();

        self::assertStringContainsString("msgid \"Stable entry\"\nmsgstr \"Stabiler Eintrag\"", $content);
        self::assertStringNotContainsString('fuzzy', $content);
    }

    /**
     * The header and the comment block above it survive.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function keepsTheHeaderOfTheCatalogue(): void
    {
        $this->requireTools();

        $before = $this->workspace->read();

        $this->apply();

        self::assertStringStartsWith(
            "# German translations for the fixture.\n# Copyright (C) Rico Sonntag\nmsgid \"\"\nmsgstr \"\"\n",
            $this->workspace->read(),
        );
        self::assertStringContainsString('"Plural-Forms: nplurals=2; plural=(n != 1);\n"', $this->workspace->read());
        self::assertStringContainsString('"Language: de\n"', $before);
        self::assertStringContainsString('"Language: de\n"', $this->workspace->read());
    }

    /**
     * A further merge against the template leaves the written catalogue unchanged and msgfmt accepts it.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function writesWhatTheNextMergeWouldWrite(): void
    {
        $this->requireTools();

        $this->apply();

        $written = $this->workspace->read();

        self::assertSame(0, $this->workspace->merge());
        self::assertSame($written, $this->workspace->read());
        self::assertSame(0, $this->workspace->check());
    }

    /**
     * No staging file is left next to the catalogue.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function leavesNoWorkingFileBehind(): void
    {
        $this->requireTools();

        $this->apply();

        self::assertSame(['messages.po'], $this->workspace->files());
    }

    /**
     * The working directory is removed after a successful run.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function removesItsWorkingDirectory(): void
    {
        $this->requireTools();

        $scratch = $this->workspace->emptyDirectory();

        $resolution = (new PunctuationResolver())->resolve($this->workspace->read());

        (new CatalogueToolchain($scratch))->apply(
            $this->workspace->catalogue,
            $this->workspace->template,
            $resolution,
            static fn (): bool => true,
        );

        self::assertSame([], $this->entriesOf($scratch));
    }

    /**
     * A given directory that does not exist is reported instead of falling back to the system one.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function reportsAWorkingDirectoryThatDoesNotExist(): void
    {
        $this->expectException(CatalogueToolException::class);
        $this->expectExceptionMessageMatches('/working directory/');

        (new CatalogueToolchain($this->workspace->emptyDirectory() . '/does-not-exist'))->apply(
            $this->workspace->catalogue,
            $this->workspace->template,
            new Resolution("msgid \"\"\nmsgstr \"\"\n", '', 0, [], [], []),
            static fn (): bool => true,
        );
    }

    /**
     * A working file that is stored only in part stops the run before a tool reads it as a shorter, valid file.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function failsBeforeAnyToolRunsWhenAWorkingFileIsStoredInPart(): void
    {
        stream_wrapper_register('shortwrite', ShortWriteStream::class);

        try {
            $this->expectException(CatalogueToolException::class);
            $this->expectExceptionMessageMatches('/Could not write the overlay of the entries to correct/');

            (new CatalogueToolchain('shortwrite://root'))->apply(
                $this->workspace->catalogue,
                $this->workspace->template,
                new Resolution("msgid \"\"\nmsgstr \"\"\n", '', 1, [], [], []),
                static fn (): bool => true,
            );
        } finally {
            stream_wrapper_unregister('shortwrite');
        }
    }

    /**
     * A line feed in the path of the working directory does not start a line of its own in the exception.
     */
    #[Test]
    public function keepsALineFeedOfTheWorkingDirectoryOutOfTheException(): void
    {
        try {
            (new CatalogueToolchain($this->workspace->emptyDirectory() . "/gone\n::error::x"))->apply(
                $this->workspace->catalogue,
                $this->workspace->template,
                new Resolution("msgid \"\"\nmsgstr \"\"\n", '', 0, [], [], []),
                static fn (): bool => true,
            );

            self::fail('The toolchain should have failed.');
        } catch (CatalogueToolException $exception) {
            self::assertStringContainsString('gone?::error::x', $exception->getMessage());
            self::assertStringNotContainsString("\n::error::", $exception->getMessage());
        }
    }

    /**
     * A working directory that cannot be created below an existing one raises no PHP warning.
     */
    #[Test]
    public function raisesNoWarningWhenTheWorkingDirectoryCannotBeCreated(): void
    {
        $root = $this->workspace->emptyDirectory();

        chmod($root, 0500);

        $warnings = [];

        set_error_handler(
            static function (int $severity, string $message) use (&$warnings): bool {
                $warnings[] = $message;

                return true;
            },
        );

        try {
            if (is_writable($root)) {
                self::markTestSkipped('The directory stays writable for this user.');
            }

            (new CatalogueToolchain($root))->apply(
                $this->workspace->catalogue,
                $this->workspace->template,
                new Resolution("msgid \"\"\nmsgstr \"\"\n", '', 0, [], [], []),
                static fn (): bool => true,
            );

            self::fail('The toolchain should have failed.');
        } catch (CatalogueToolException $exception) {
            self::assertStringContainsString('Could not create the working directory', $exception->getMessage());
        } finally {
            restore_error_handler();
            chmod($root, 0700);
        }

        self::assertSame([], $warnings);
    }

    /**
     * Working files that cannot be removed raise no PHP warning that replaces the failure of the tool.
     */
    #[Test]
    public function raisesNoWarningWhenTheWorkingDirectoryCannotBeRemoved(): void
    {
        $root = $this->workspace->emptyDirectory();
        $stub = $root . '/stub';

        mkdir($stub, 0700, true);
        // The stub leaves a directory in the working directory, which the cleanup cannot remove.
        file_put_contents(
            $stub . '/msgcat',
            <<<'SH'
                #!/bin/sh
                for argument in "$@"; do
                    case "$argument" in
                        --output-file=*) path="${argument#--output-file=}"; directory="${path%/*}" ;;
                    esac
                done
                /bin/mkdir "$directory/left"
                exit 3
                SH,
        );
        chmod($stub . '/msgcat', 0700);

        $warnings = [];
        $original = (string) getenv('PATH');

        set_error_handler(
            static function (int $severity, string $message) use (&$warnings): bool {
                $warnings[] = $message;

                return true;
            },
        );
        putenv('PATH=' . $stub);

        try {
            (new CatalogueToolchain($root))->apply(
                $this->workspace->catalogue,
                $this->workspace->template,
                new Resolution("msgid \"\"\nmsgstr \"\"\n", '', 0, [], [], []),
                static fn (): bool => true,
            );

            self::fail('The toolchain should have failed.');
        } catch (CatalogueToolException $exception) {
            self::assertStringContainsString('exit status 3', $exception->getMessage());
        } finally {
            putenv('PATH=' . $original);
            restore_error_handler();
        }

        self::assertSame([], $warnings);
    }

    /**
     * What a failing tool writes to its error output reaches the exception.
     */
    #[Test]
    public function reportsTheErrorOutputOfAFailingTool(): void
    {
        $directory = $this->workspace->emptyDirectory() . '/stub';

        mkdir($directory, 0700, true);
        file_put_contents($directory . '/msgcat', "#!/bin/sh\nprintf 'broken input\\n' >&2\nexit 3\n");
        chmod($directory . '/msgcat', 0700);

        $exception = $this->failureWithPath($directory);

        self::assertStringContainsString('exit status 3. broken input', $exception->getMessage());
    }

    /**
     * What a failing tool writes to both of its streams reaches the exception without either part overwriting the other.
     */
    #[Test]
    public function keepsTheOutputAndTheErrorOutputOfAFailingToolApart(): void
    {
        $directory = $this->workspace->emptyDirectory() . '/stub';

        mkdir($directory, 0700, true);
        file_put_contents(
            $directory . '/msgcat',
            <<<'SH'
                #!/bin/sh
                printf 'first\n'
                printf 'second-and-longer\n' >&2
                printf 'third\n'
                exit 3
                SH,
        );
        chmod($directory . '/msgcat', 0700);

        $message = $this->failureWithPath($directory)->getMessage();

        self::assertStringContainsString('first?second-and-longer?third', $message);
    }

    /**
     * The working directory is removed also when a tool fails.
     */
    #[Test]
    public function removesItsWorkingDirectoryWhenAToolFails(): void
    {
        $scratch = $this->workspace->emptyDirectory();

        try {
            (new CatalogueToolchain($scratch))->apply(
                $this->workspace->catalogue . '.missing',
                $this->workspace->template,
                new Resolution("msgid \"\"\nmsgstr \"\"\n", '', 0, [], [], []),
                static fn (): bool => true,
            );

            self::fail('The toolchain should have failed.');
        } catch (CatalogueToolException) {
            self::assertSame([], $this->entriesOf($scratch));
        }
    }

    /**
     * A catalogue in a directory that is not writable raises the toolchain exception.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function failsWhenTheCatalogueCannotBeReplaced(): void
    {
        $this->requireTools();

        $directory = dirname($this->workspace->catalogue);

        chmod($directory, 0500);

        try {
            if (is_writable($directory)) {
                self::markTestSkipped('The directory stays writable for this user.');
            }

            $resolution = (new PunctuationResolver())->resolve($this->workspace->read());

            $this->expectException(CatalogueToolException::class);
            $this->expectExceptionMessageMatches('/^Could not write /');

            (new CatalogueToolchain())->apply(
                $this->workspace->catalogue,
                $this->workspace->template,
                $resolution,
                static fn (): bool => true,
            );
        } finally {
            chmod($directory, 0700);
        }
    }

    /**
     * The output of a failing tool reaches the exception without its control characters.
     */
    #[Test]
    public function keepsControlCharactersOfAToolOutputOutOfTheException(): void
    {
        $exception = $this->failureWithPath($this->stubDirectory());

        self::assertStringContainsString('exit status 3. bad ?[31m', $exception->getMessage());
    }

    /**
     * A line feed in the path of a catalogue does not start a line of its own in the exception.
     */
    #[Test]
    public function keepsALineFeedOfACataloguePathOutOfTheException(): void
    {
        $exception = $this->failureWithPath($this->stubDirectory(), $this->workspace->catalogue . "\n::error::x");

        self::assertStringContainsString('?::error::x', $exception->getMessage());
        self::assertStringNotContainsString("\n::error::", $exception->getMessage());
    }

    /**
     * A failure to replace the catalogue raises no PHP warning, whose text would carry the raw path.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function raisesNoWarningWhenTheCatalogueCannotBeReplaced(): void
    {
        $this->requireTools();

        $directory = $this->workspace->emptyDirectory() . "/loc\n::error::x";
        $catalogue = $directory . '/messages.po';
        $stub      = $this->workspace->emptyDirectory() . '/stub';

        mkdir($directory, 0700, true);
        copy($this->workspace->catalogue, $catalogue);

        // The stub stands in for the last tool and puts a directory where the catalogue was.
        mkdir($stub, 0700, true);
        file_put_contents($stub . '/msgmerge', "#!/bin/sh\nrm -f '" . $catalogue . "'\nmkdir '" . $catalogue . "'\n");
        chmod($stub . '/msgmerge', 0700);

        $warnings = [];
        $original = (string) getenv('PATH');

        set_error_handler(
            static function (int $severity, string $message) use (&$warnings): bool {
                $warnings[] = $message;

                return true;
            },
        );
        putenv('PATH=' . $stub . ':' . $original);

        try {
            (new CatalogueToolchain())->apply(
                $catalogue,
                $this->workspace->template,
                (new PunctuationResolver())->resolve($this->workspace->read()),
                static fn (): bool => true,
            );

            self::fail('The toolchain should have failed.');
        } catch (CatalogueToolException $exception) {
            self::assertStringContainsString('Could not write', $exception->getMessage());
            self::assertStringNotContainsString("\n::error::", $exception->getMessage());
        } finally {
            putenv('PATH=' . $original);
            restore_error_handler();
        }

        self::assertSame([], $warnings);
        self::assertSame([], array_values(array_diff($this->entriesOf($directory), ['messages.po'])));
    }

    /**
     * A tool that cannot be started raises the toolchain exception without a PHP warning.
     */
    #[Test]
    public function failsWhenAToolIsNotInstalled(): void
    {
        $exception = $this->failureWithPath('/nonexistent');

        self::assertSame('Could not start msgcat, are the gettext tools installed?', $exception->getMessage());
    }

    /**
     * A link committed under the plain name of the catalogue plus the staging suffix is not written through.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function doesNotWriteThroughALinkAtAGuessableStagingName(): void
    {
        $this->requireTools();

        $victim = $this->workspace->emptyDirectory() . '/victim.txt';

        file_put_contents($victim, 'victim');
        symlink($victim, $this->workspace->catalogue . '.resolving');

        $this->apply();

        self::assertSame('victim', file_get_contents($victim));
        self::assertFalse(is_link($this->workspace->catalogue));
        self::assertStringContainsString('msgstr "Geburten · pro Jahr"', $this->workspace->read());
    }

    /**
     * A catalogue in a directory named like a tool option is rewritten as if it had been given by its
     * absolute path, and not read as an option.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function rewritesACatalogueWhoseRelativePathStartsWithADash(): void
    {
        $this->requireTools();

        $base       = $this->workspace->emptyDirectory();
        $directory  = $base . '/--output-file=x';
        $control    = $base . '/control';
        $resolution = (new PunctuationResolver())->resolve($this->workspace->read());

        mkdir($directory, 0700, true);
        mkdir($control, 0700, true);
        copy($this->workspace->catalogue, $directory . '/messages.po');
        copy($this->workspace->catalogue, $control . '/messages.po');

        (new CatalogueToolchain())->apply($control . '/messages.po', $this->workspace->template, $resolution, static fn (): bool => true);

        $original = getcwd();

        chdir($base);

        try {
            (new CatalogueToolchain())->apply('--output-file=x/messages.po', $this->workspace->template, $resolution, static fn (): bool => true);
        } finally {
            chdir($original === false ? '/' : $original);
        }

        self::assertSame(
            file_get_contents($control . '/messages.po'),
            file_get_contents($directory . '/messages.po'),
        );
    }

    /**
     * A template given by a relative path that starts with a dash is used as if it had been given by its
     * absolute path, and not read as an option.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function mergesAgainstATemplateWhoseRelativePathStartsWithADash(): void
    {
        $this->requireTools();

        $base       = $this->workspace->emptyDirectory();
        $templates  = $base . '/--x';
        $control    = $base . '/control';
        $resolution = (new PunctuationResolver())->resolve($this->workspace->read());

        mkdir($templates, 0700, true);
        mkdir($control, 0700, true);
        copy($this->workspace->template, $templates . '/messages.pot');
        copy($this->workspace->catalogue, $control . '/messages.po');

        $subject = $base . '/subject';

        mkdir($subject, 0700, true);
        copy($this->workspace->catalogue, $subject . '/messages.po');

        (new CatalogueToolchain())->apply($control . '/messages.po', $this->workspace->template, $resolution, static fn (): bool => true);

        $original = getcwd();

        chdir($base);

        try {
            (new CatalogueToolchain())->apply($subject . '/messages.po', '--x/messages.pot', $resolution, static fn (): bool => true);
        } finally {
            chdir($original === false ? '/' : $original);
        }

        self::assertSame(
            file_get_contents($control . '/messages.po'),
            file_get_contents($subject . '/messages.po'),
        );
    }

    /**
     * The translator comments of a resolved entry are still there after the catalogue was written.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function keepsTheTranslatorCommentsOfAResolvedEntry(): void
    {
        $this->requireTools();

        $this->workspace->write(
            str_replace("#: src/A.php:10\n", "# Translator note\n#: src/A.php:10\n", $this->workspace->read()),
        );

        $this->apply();

        self::assertStringContainsString(
            "# Translator note\n#: src/A.php:10\nmsgid \"Births · per year\"",
            $this->workspace->read(),
        );
    }

    /**
     * The working files are removed below a directory whose name holds glob characters.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    #[Test]
    public function removesItsWorkingFilesBelowADirectoryWithGlobCharacters(): void
    {
        $this->requireTools();

        $scratch = $this->workspace->emptyDirectory() . '/tmp[1]';

        mkdir($scratch, 0700, true);

        $resolution = (new PunctuationResolver())->resolve($this->workspace->read());

        (new CatalogueToolchain($scratch))->apply(
            $this->workspace->catalogue,
            $this->workspace->template,
            $resolution,
            static fn (): bool => true,
        );

        self::assertSame([], $this->entriesOf($scratch));
    }

    /**
     * A tool that exits with a failure raises the toolchain exception with the command and its output.
     *
     * @throws CatalogueToolException
     */
    #[Test]
    public function failsWhenAToolFails(): void
    {
        $this->requireTools();

        $this->expectException(CatalogueToolException::class);
        $this->expectExceptionMessageMatches('/^msgcat .* failed with exit status [1-9]\d*\. /s');

        (new CatalogueToolchain())->apply(
            $this->workspace->catalogue . '.missing',
            $this->workspace->template,
            new Resolution("msgid \"\"\nmsgstr \"\"\n", '', 0, [], [], []),
            static fn (): bool => true,
        );
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
     * Makes a directory with a msgcat stub that prints control characters and fails.
     *
     * @return string The directory that holds the stub
     */
    private function stubDirectory(): string
    {
        $directory = $this->workspace->emptyDirectory() . '/stub';

        mkdir($directory, 0700, true);
        file_put_contents($directory . '/msgcat', "#!/bin/sh\nprintf 'bad \\033[31m\\r\\n'\nexit 3\n");
        chmod($directory . '/msgcat', 0700);

        return $directory;
    }

    /**
     * Runs the toolchain with another search path for the tools and returns the exception it raises.
     *
     * @param string      $path      The search path the tools are looked up in
     * @param string|null $catalogue The catalogue to apply to, the one of the scratch copy by default
     *
     * @return CatalogueToolException The exception the toolchain raised
     */
    private function failureWithPath(string $path, ?string $catalogue = null): CatalogueToolException
    {
        $original = (string) getenv('PATH');

        putenv('PATH=' . $path);

        try {
            (new CatalogueToolchain())->apply(
                $catalogue ?? $this->workspace->catalogue,
                $this->workspace->template,
                new Resolution("msgid \"\"\nmsgstr \"\"\n", '', 0, [], [], []),
                static fn (): bool => true,
            );
        } catch (CatalogueToolException $exception) {
            return $exception;
        } finally {
            putenv('PATH=' . $original);
        }

        self::fail('The toolchain should have failed.');
    }

    /**
     * Lists what a directory holds.
     *
     * @param string $directory The directory to list
     *
     * @return list<string> The names inside it, without the dot entries
     */
    private function entriesOf(string $directory): array
    {
        $names = scandir($directory);

        return array_values(array_diff($names === false ? [] : $names, ['.', '..']));
    }

    /**
     * Resolves the fixture catalogue and writes the result into the copy.
     *
     * @throws CatalogueToolException
     * @throws CatalogueLayoutException
     */
    private function apply(): void
    {
        $toolchain  = new CatalogueToolchain();
        $resolution = (new PunctuationResolver())->resolve($toolchain->normalise($this->workspace->catalogue));

        $toolchain->apply(
            $this->workspace->catalogue,
            $this->workspace->template,
            $resolution,
            static fn (): bool => true,
        );
    }
}
