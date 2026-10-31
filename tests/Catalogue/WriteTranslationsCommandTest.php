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
use MagicSunday\Webtrees\ModuleBase\Catalogue\CatalogueEntryReader;
use MagicSunday\Webtrees\ModuleBase\Catalogue\CatalogueFile;
use MagicSunday\Webtrees\ModuleBase\Catalogue\CatalogueToolchain;
use MagicSunday\Webtrees\ModuleBase\Catalogue\ParsedEntry;
use MagicSunday\Webtrees\ModuleBase\Catalogue\PrintableText;
use MagicSunday\Webtrees\ModuleBase\Catalogue\Resolution;
use MagicSunday\Webtrees\ModuleBase\Catalogue\TranslationFiller;
use MagicSunday\Webtrees\ModuleBase\Catalogue\WriteTranslationsCommand;
use MagicSunday\Webtrees\ModuleBase\Test\Double\GettextWorkspace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function basename;
use function chmod;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function function_exists;
use function getenv;
use function json_encode;
use function mkdir;
use function posix_geteuid;
use function putenv;
use function str_contains;

use const JSON_THROW_ON_ERROR;

/**
 * Pins what the command writes into a catalogue and what it refuses.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(WriteTranslationsCommand::class)]
#[UsesClass(CatalogueBlockParser::class)]
#[UsesClass(CatalogueEntryReader::class)]
#[UsesClass(CatalogueFile::class)]
#[UsesClass(CatalogueToolchain::class)]
#[UsesClass(ParsedEntry::class)]
#[UsesClass(PrintableText::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(TranslationFiller::class)]
final class WriteTranslationsCommandTest extends TestCase
{
    /**
     * A value that would start a workflow command in a CI log and turn the text around if it reached the report as it
     * is, which a contributor can put into a path, a locale or a source text.
     */
    private const string HOSTILE = "##[error]\u{202E}x";

    /**
     * The scratch directory the running test works in.
     */
    private GettextWorkspace $workspace;

    /**
     * Creates the scratch directory before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = new GettextWorkspace();
    }

    /**
     * Removes the scratch directory after each test.
     */
    protected function tearDown(): void
    {
        $this->workspace->remove();

        parent::tearDown();
    }

    /**
     * The empty entries get their translations with the forms of the locale, and a second run changes nothing.
     */
    #[Test]
    public function writesTheMissingTranslationsAndChangesNothingOnASecondRun(): void
    {
        $this->requireTools();

        $catalogue = $this->prepare($this->workspace->catalogue, 2);
        $file      = $this->translations(['Hello' => ['de' => 'Hallo'], 'One' => ['de' => ['Ding', 'Dinge']]]);

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run([$file, $this->workspace->template, $catalogue], $this->collect($lines));

        self::assertSame(0, $status);
        self::assertContains('  ✔ write-translations: written=2, refused=0', $lines);

        $content = $this->workspace->read();

        self::assertStringContainsString('msgstr "Hallo"', $content);
        self::assertStringContainsString('msgstr[1] "Dinge"', $content);

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run([$file, $this->workspace->template, $catalogue], $this->collect($lines));

        self::assertSame(0, $status);
        self::assertSame(['  ✔ write-translations: written=0, refused=0'], $lines);
        self::assertSame($content, $this->workspace->read());
    }

    /**
     * An entry with the wrong number of forms for the locale is refused and the run exits with 1, while the other
     * entries are written.
     */
    #[Test]
    public function refusesAnEntryWithTheWrongNumberOfForms(): void
    {
        $this->requireTools();

        $catalogue = $this->prepare($this->workspace->addCatalogue('ru', ''), 3);
        $file      = $this->translations(['Hello' => ['ru' => 'Привет'], 'One' => ['ru' => ['a', 'b']]]);

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run([$file, $this->workspace->template, $catalogue], $this->collect($lines));

        self::assertSame(1, $status);
        self::assertContains('  ✘ ru: "One" needs 3 forms and the translations file lists 2', $lines);
        self::assertContains('  ✔ write-translations: written=1, refused=1', $lines);
    }

    /**
     * Too few arguments give the usage and the status 2, and an unusable translations file fails the run.
     */
    #[Test]
    public function explainsTheUsageAndFailsForAnUnusableTranslationsFile(): void
    {
        $lines  = [];
        $status = (new WriteTranslationsCommand())->run(['translations.json'], $this->collect($lines));

        self::assertSame(2, $status);
        self::assertStringStartsWith('Usage: write-translations', $lines[0]);

        $broken = $this->workspace->emptyDirectory() . '/broken.json';

        file_put_contents($broken, '{"Hello": "not a map"}');

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run([$broken, $this->workspace->template, $this->workspace->catalogue], $this->collect($lines));

        self::assertSame(1, $status);
        self::assertStringContainsString('is not a JSON object of source texts', $lines[0]);
    }

    /**
     * A source text that reads as a number is written like any other, and a catalogue after a missing one is still
     * written.
     */
    #[Test]
    public function writesANumericSourceTextAndGoesOnAfterAMissingCatalogue(): void
    {
        $this->requireTools();

        $header = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n";

        file_put_contents($this->workspace->template, $header . "msgid \"404\"\nmsgstr \"\"\n");
        file_put_contents($this->workspace->catalogue, $header . "msgid \"404\"\nmsgstr \"\"\n");
        GettextWorkspace::mergeFile($this->workspace->catalogue, $this->workspace->template);

        $file  = $this->translations(['404' => ['de' => 'Nicht gefunden']]);
        $lines = [];

        $status = (new WriteTranslationsCommand())->run(
            [$file, $this->workspace->template, $this->workspace->catalogue . '.missing', $this->workspace->catalogue],
            $this->collect($lines),
        );

        self::assertSame(1, $status);
        self::assertContains('  ✘ ' . $this->workspace->catalogue . '.missing not found', $lines);
        self::assertContains('  ✔ write-translations: written=1, refused=1', $lines);
        self::assertStringContainsString('msgstr "Nicht gefunden"', $this->workspace->read());
    }

    /**
     * An object where a list of forms is expected is refused as a wrong shape.
     */
    #[Test]
    public function refusesAnObjectWhereAListOfFormsIsExpected(): void
    {
        $broken = $this->workspace->emptyDirectory() . '/object.json';

        file_put_contents($broken, '{"File": {"de": {"one": "Datei", "other": "Dateien"}}}');

        $lines = [];

        $status = (new WriteTranslationsCommand())->run(
            [$broken, $this->workspace->template, $this->workspace->catalogue],
            $this->collect($lines),
        );

        self::assertSame(1, $status);
        self::assertStringContainsString('is not a JSON object of source texts', $lines[0]);
    }

    /**
     * An entry that webtrees core translates itself stays empty, the other entries are written, and a core template
     * that cannot be read fails the run with a line of its own.
     */
    #[Test]
    public function leavesAnEntryOfTheCoreTemplateAloneAndNamesAnUnreadableCoreTemplate(): void
    {
        $this->requireTools();

        $catalogue = $this->prepare($this->workspace->catalogue, 2);
        $core      = $this->workspace->emptyDirectory() . '/core.pot';
        $file      = $this->translations(['Hello' => ['de' => 'Hallo'], 'One' => ['de' => ['Ding', 'Dinge']]]);

        file_put_contents(
            $core,
            "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"Hello\"\nmsgstr \"\"\n",
        );

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run(
            ['--core-owned=' . $core, $file, $this->workspace->template, $catalogue],
            $this->collect($lines),
        );

        self::assertSame(0, $status);
        self::assertContains('  ✔ write-translations: written=1, refused=0', $lines);
        self::assertStringNotContainsString('msgstr "Hallo"', $this->workspace->read());
        self::assertStringContainsString('msgstr[1] "Dinge"', $this->workspace->read());

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run(
            ['--core-owned=' . $core . '.absent', $file, $this->workspace->template, $catalogue],
            $this->collect($lines),
        );

        self::assertSame(1, $status);
        self::assertStringContainsString('cannot be read', $lines[0]);
    }

    /**
     * A finished catalogue that differs from the plan is not put in place, the catalogue is named and the run exits
     * with 1.
     *
     * @param string $change The sed expression with which the stub changes what the last tool wrote
     */
    #[Test]
    #[DataProvider('changesOfTheFinishedResult')]
    public function leavesACatalogueAsItIsWhenTheFinishedResultDiffersFromThePlan(string $change): void
    {
        $this->requireTools();

        $catalogue = $this->prepare($this->workspace->catalogue, 2);
        $before    = (string) file_get_contents($catalogue);
        $file      = $this->translations(['Hello' => ['de' => 'Hallo']]);
        $lines     = [];

        $status = $this->runWithChangedResult($change, [$file, $this->workspace->template, $catalogue], $lines);

        self::assertSame(1, $status);
        self::assertContains(
            '  ✘ ' . $catalogue . ' was left as it is, because the written result differs from the translations given or from the entries the run did not touch',
            $lines,
        );
        self::assertSame($before, (string) file_get_contents($catalogue));
    }

    /**
     * What a misbehaving last tool can change in the finished catalogue, each one a reason to refuse it.
     *
     * @return array<string, array{string}>
     */
    public static function changesOfTheFinishedResult(): array
    {
        return [
            'translation text'     => ['s/msgstr "Hallo"/msgstr "Falsch"/'],
            'entry removed'        => ['/^msgid "One"$/,$d'],
            'entry added'          => ['s/^msgid "One"$/msgid "Extra"\\nmsgstr ""\\n\\n&/'],
            'entry renamed'        => ['s/^msgid "One"$/msgid "Other"/'],
            'fuzzy flag set'       => ['s/^msgid "Hello"$/#, fuzzy\n&/'],
            'plural text replaced' => ['s/^msgid_plural "Many"$/msgid_plural "Lots"/'],
        ];
    }

    /**
     * A catalogue that is not what a merge with the template writes is named with the advice, and the run exits
     * with 1.
     */
    #[Test]
    public function namesACatalogueThatIsNotMergedFromTheTemplate(): void
    {
        $this->requireTools();

        $catalogue = $this->prepare($this->workspace->catalogue, 2);

        file_put_contents($this->workspace->template, file_get_contents($this->workspace->template) . "\nmsgid \"Extra\"\nmsgstr \"\"\n");

        $file  = $this->translations(['Hello' => ['de' => 'Hallo']]);
        $lines = [];

        $status = (new WriteTranslationsCommand())->run([$file, $this->workspace->template, $catalogue], $this->collect($lines));

        self::assertSame(1, $status);
        self::assertStringContainsString('cannot be used as it is, write it once with `msgmerge --previous --output-file=', $lines[0]);
    }

    /**
     * A catalogue whose header declares another number of plural forms than the locale has is refused before
     * anything is written.
     */
    #[Test]
    public function refusesACatalogueWhoseHeaderDisagreesWithTheLocale(): void
    {
        $this->requireTools();

        $catalogue = $this->prepare($this->workspace->addCatalogue('ru', ''), 2);
        $before    = (string) file_get_contents($catalogue);
        $file      = $this->translations(['One' => ['ru' => ['a', 'b', 'c']]]);

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run([$file, $this->workspace->template, $catalogue], $this->collect($lines));

        self::assertSame(1, $status);
        self::assertStringContainsString('declares 2 plural forms but the locale "ru" has 3', $lines[0]);
        self::assertSame($before, (string) file_get_contents($catalogue));
    }

    /**
     * A catalogue whose header declares no plural rule is refused when a plural entry would be written, even if a
     * singular entry comes first in the plan, because the result would hold plural forms with no rule to select them.
     */
    #[Test]
    public function refusesAPluralEntryInACatalogueWhoseHeaderDeclaresNoPluralRule(): void
    {
        $this->requireTools();

        $header  = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n";
        $entries = "msgid \"Hello\"\nmsgstr \"\"\n\nmsgid \"One\"\nmsgid_plural \"Many\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n";

        file_put_contents($this->workspace->template, $header . $entries);
        file_put_contents($this->workspace->catalogue, $header . $entries);
        GettextWorkspace::mergeFile($this->workspace->catalogue, $this->workspace->template);

        $before = (string) file_get_contents($this->workspace->catalogue);
        $file   = $this->translations(['Hello' => ['de' => 'Hallo'], 'One' => ['de' => ['Eins', 'Viele']]]);

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run(
            [$file, $this->workspace->template, $this->workspace->catalogue],
            $this->collect($lines),
        );

        self::assertSame(1, $status);
        self::assertStringContainsString('declares no plural forms', $lines[0]);
        self::assertSame($before, (string) file_get_contents($this->workspace->catalogue));
    }

    /**
     * Only the Plural-Forms field of the header declares the plural rule, so a number in a comment of the header is
     * not taken for it.
     */
    #[Test]
    public function readsThePluralRuleFromTheFieldAndNotFromAComment(): void
    {
        $this->requireTools();

        $header = "# Translators: an older draft used nplurals=3 here.\n"
            . "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n"
            . "\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n\n";
        $entries = "msgid \"One\"\nmsgid_plural \"Many\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n";

        file_put_contents($this->workspace->template, $header . $entries);
        file_put_contents($this->workspace->catalogue, $header . $entries);
        GettextWorkspace::mergeFile($this->workspace->catalogue, $this->workspace->template);

        $file = $this->translations(['One' => ['de' => ['Eins', 'Viele']]]);

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run(
            [$file, $this->workspace->template, $this->workspace->catalogue],
            $this->collect($lines),
        );

        self::assertSame(0, $status);
        self::assertContains('  ✔ write-translations: written=1, refused=0', $lines);
    }

    /**
     * Source texts that are the numbers from 0 on are a JSON object like any other and not a list.
     */
    #[Test]
    public function acceptsSourceTextsThatReadAsAListOfNumbers(): void
    {
        $this->requireTools();

        $header  = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n";
        $entries = "msgid \"0\"\nmsgstr \"\"\n\nmsgid \"1\"\nmsgstr \"\"\n";

        file_put_contents($this->workspace->template, $header . $entries);
        file_put_contents($this->workspace->catalogue, $header . $entries);
        GettextWorkspace::mergeFile($this->workspace->catalogue, $this->workspace->template);

        // PHP encodes an array with the keys 0 and 1 as a JSON list, so the object is written out.
        $file = $this->workspace->emptyDirectory() . '/numbers.json';

        file_put_contents($file, '{"0":{"de":"Null"},"1":{"de":"Eins"}}');

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run(
            [$file, $this->workspace->template, $this->workspace->catalogue],
            $this->collect($lines),
        );

        self::assertSame(0, $status);
        self::assertContains('  ✔ write-translations: written=2, refused=0', $lines);
    }

    /**
     * A locale whose catalogue was refused or is missing is not reported as a locale without a catalogue, and the
     * catalogue after it is still written.
     *
     * @param bool $missing True for a catalogue that does not exist, false for one the header of which is refused
     */
    #[Test]
    #[DataProvider('unusableCatalogues')]
    public function doesNotReportTheLocaleOfAnUnusableCatalogueAsMissing(bool $missing): void
    {
        $this->requireTools();

        $german  = $this->prepare($this->workspace->catalogue, 2);
        $russian = $missing
            ? dirname($this->workspace->addCatalogue('ru', '')) . '/absent.po'
            : $this->prepare($this->workspace->addCatalogue('ru', ''), 2);
        $file = $this->translations(['Hello' => ['de' => 'Hallo', 'ru' => 'Привет']]);

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run(
            [$file, $this->workspace->template, $russian, $german],
            $this->collect($lines),
        );

        self::assertSame(1, $status);
        self::assertContains('  ✔ write-translations: written=1, refused=1', $lines);
    }

    /**
     * The two ways a catalogue of the run can be unusable.
     *
     * @return array<string, array{bool}>
     */
    public static function unusableCatalogues(): array
    {
        return [
            'missing'                              => [true],
            'header declares another plural count' => [false],
        ];
    }

    /**
     * A key of the translations file that matches no entry of any catalogue is named and fails the run.
     */
    #[Test]
    public function namesAKeyThatMatchesNoEntry(): void
    {
        $this->requireTools();

        $catalogue = $this->prepare($this->workspace->catalogue, 2);
        $file      = $this->translations(['Hello' => ['de' => 'Hallo'], 'Typo' => ['de' => 'Tippfehler']]);

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run([$file, $this->workspace->template, $catalogue], $this->collect($lines));

        self::assertSame(1, $status);
        self::assertContains('  ✘ "Typo" of the translations file matches no entry of the catalogues', $lines);
        self::assertContains('  ✔ write-translations: written=1, refused=1', $lines);
    }

    /**
     * An empty translation is named instead of being skipped without a word.
     */
    #[Test]
    public function namesAnEmptyTranslation(): void
    {
        $this->requireTools();

        $catalogue = $this->prepare($this->workspace->catalogue, 2);
        $file      = $this->translations(['Hello' => ['de' => '']]);

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run([$file, $this->workspace->template, $catalogue], $this->collect($lines));

        self::assertSame(1, $status);
        self::assertContains('  ✘ de: "Hello" has an empty translation', $lines);
    }

    /**
     * A translations file that is missing is named and fails the run.
     */
    #[Test]
    public function namesAMissingTranslationsFile(): void
    {
        $lines  = [];
        $status = (new WriteTranslationsCommand())->run(
            [$this->workspace->emptyDirectory() . '/absent.json', $this->workspace->template, $this->workspace->catalogue],
            $this->collect($lines),
        );

        self::assertSame(1, $status);
        self::assertStringContainsString('absent.json', $lines[0]);
    }

    /**
     * A translations file of the wrong shape is refused with a line of its own, whichever branch of the shape check it
     * takes, and a file of the right shape is accepted.
     *
     * @param string $json     The content of the translations file
     * @param int    $expected The expected exit status
     */
    #[Test]
    #[DataProvider('shapes')]
    public function checksTheShapeOfTheTranslationsFile(string $json, int $expected): void
    {
        $this->requireTools();

        $catalogue = $this->prepare($this->workspace->catalogue, 2);
        $path      = $this->workspace->emptyDirectory() . '/translations.json';

        file_put_contents($path, $json);

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run([$path, $this->workspace->template, $catalogue], $this->collect($lines));

        self::assertSame($expected, $status);

        $refused = array_filter(
            $lines,
            static fn (string $line): bool => str_contains($line, 'is not a JSON object of source texts'),
        );

        self::assertCount($expected, $refused);
    }

    /**
     * The shapes the translations file may have.
     *
     * @return array<string, array{string, int}>
     */
    public static function shapes(): array
    {
        return [
            'invalid JSON'                       => ['{"Hello":', 1],
            'top level list'                     => ['[{"de":"Hallo"}]', 1],
            'locale layer missing'               => ['{"One":["Ding","Dinge"]}', 1],
            'form that is not a string'          => ['{"One":{"de":["a",1]}}', 1],
            'locale value that is not text'      => ['{"Hello":{"de":5}}', 1],
            'NUL in a translation'               => ['{"Hello":{"de":"a\\u0000b"}}', 1],
            'context separator in a translation' => ['{"Hello":{"de":"a\\u0004b"}}', 1],
            'backslash and u0000 as text'        => ['{"Hello":{"de":"a\\\\u0000b"}}', 0],
        ];
    }

    /**
     * A locale of the translations file that matches no catalogue is named and fails the run.
     */
    #[Test]
    public function namesALocaleThatMatchesNoCatalogue(): void
    {
        $this->requireTools();

        $catalogue = $this->prepare($this->workspace->catalogue, 2);
        $file      = $this->translations(['Hello' => ['de' => 'Hallo', 'fr_CA' => 'Allo']]);

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run([$file, $this->workspace->template, $catalogue], $this->collect($lines));

        self::assertSame(1, $status);
        self::assertContains('  ✘ the locale "fr_CA" of the translations file matches no catalogue', $lines);
    }

    /**
     * A path with a dot component takes the locale from the resolved directory and not from the working directory.
     */
    #[Test]
    public function takesTheLocaleFromTheResolvedDirectoryOfADottedPath(): void
    {
        $this->requireTools();

        $catalogue = $this->prepare($this->workspace->addCatalogue('fr', ''), 2);
        $file      = $this->translations(['Hello' => ['fr' => 'Bonjour']]);
        $dotted    = dirname($catalogue) . '/./' . basename($catalogue);

        $lines  = [];
        $status = (new WriteTranslationsCommand())->run([$file, $this->workspace->template, $dotted], $this->collect($lines));

        self::assertSame(0, $status);
        self::assertStringContainsString('msgstr "Bonjour"', (string) file_get_contents($catalogue));
    }

    /**
     * The places where the report names text that a contributor controls: a path, a locale, a source text or the
     * message of a tool.
     *
     * @return array<string, array{string}>
     */
    public static function placesThatNameForeignText(): array
    {
        return [
            'the translations file is missing'              => ['translations-file-missing'],
            'the template is missing'                       => ['template-missing'],
            'the translations file has the wrong shape'     => ['translations-file-wrong-shape'],
            'the core template is missing'                  => ['core-owned-missing'],
            'a catalogue is missing'                        => ['catalogue-missing'],
            'the directory is no locale'                    => ['locale-unknown'],
            'the header declares another form count'        => ['plural-count-differs'],
            'the filler refuses an entry'                   => ['refused-reason'],
            'the header declares no plural rule'            => ['no-plural-header'],
            'the finished result differs from the plan'     => ['result-differs'],
            'the catalogue is not merged from the template' => ['not-merged'],
            'a key matches no entry'                        => ['key-matches-no-entry'],
            'a locale matches no catalogue'                 => ['locale-matches-no-catalogue'],
        ];
    }

    /**
     * Whatever a path, a locale or a source text holds is cleaned before the report names it, so a contributed
     * catalogue or file name cannot start a workflow command in a CI log or turn the text around. The places of the
     * report that name a path, the locale of a directory, a key or a source text are reached with a hostile value
     * here, and no line may keep it while the cleaned text is named. The cleaning of a tool message and of a locale
     * that was checked before is not pinned, the first is cleaned where the tool throws and the second cannot carry
     * such a value.
     *
     * @param string $scenario The place the hostile value is put into
     */
    #[Test]
    #[DataProvider('placesThatNameForeignText')]
    public function cleansForeignTextWhereverTheReportNamesIt(string $scenario): void
    {
        $this->requireTools();

        [$arguments, $named, $change] = $this->hostileScenario($scenario);

        $lines = [];

        if ($change === null) {
            (new WriteTranslationsCommand())->run($arguments, $this->collect($lines));
        } else {
            $this->runWithChangedResult($change, $arguments, $lines);
        }

        $this->assertNamesOnlyTheCleanedText($lines, $named);
    }

    /**
     * A catalogue that cannot be written is named with the message of the tool, and both are cleaned. The directory
     * is made read-only for the run, which only a user other than root feels.
     */
    #[Test]
    public function cleansTheNameOfACatalogueThatCannotBeWritten(): void
    {
        $this->requireTools();

        if (
            !function_exists('posix_geteuid')
            || (posix_geteuid() === 0)
        ) {
            self::markTestSkipped('A directory is only read-only for a user other than root.');
        }

        $catalogue = $this->prepare($this->hostileCatalogue('de'), 2);
        $file      = $this->translations(['Hello' => ['de' => 'Hallo']]);
        $lines     = [];

        chmod(dirname($catalogue), 0555);

        try {
            (new WriteTranslationsCommand())->run([$file, $this->workspace->template, $catalogue], $this->collect($lines));
        } finally {
            chmod(dirname($catalogue), 0700);
        }

        $this->assertNamesOnlyTheCleanedText($lines, [$catalogue]);
        self::assertTrue(
            $this->anyLineContains($lines, 'could not be written'),
            'the report names the catalogue that could not be written',
        );
    }

    /**
     * Builds the files of one place of the report that names foreign text.
     *
     * @param string $scenario The place the hostile value is put into
     *
     * @return array{list<string>, list<string>, string|null} The arguments of the command, the hostile values the
     *                                                        report has to name in cleaned form, and the change a
     *                                                        misbehaving last tool makes, when the scenario needs one
     */
    private function hostileScenario(string $scenario): array
    {
        $hostile = self::HOSTILE;
        $file    = $this->translations(['Hello' => ['de' => 'Hallo']]);
        $valid   = $this->prepare($this->workspace->catalogue, 2);
        $base    = $this->workspace->emptyDirectory();

        return match ($scenario) {
            'translations-file-missing' => [
                [$base . '/' . $hostile . '.json', $this->workspace->template, $valid],
                [$base . '/' . $hostile . '.json'],
                null,
            ],
            'template-missing' => [
                [$file, $base . '/' . $hostile . '.pot', $valid],
                [$base . '/' . $hostile . '.pot'],
                null,
            ],
            'translations-file-wrong-shape' => [
                [$this->shapeless($base . '/' . $hostile . '.json'), $this->workspace->template, $valid],
                [$base . '/' . $hostile . '.json'],
                null,
            ],
            'core-owned-missing' => [
                ['--core-owned=' . $base . '/' . $hostile . '.pot', $file, $this->workspace->template, $valid],
                [$base . '/' . $hostile . '.pot'],
                null,
            ],
            'catalogue-missing' => [
                [$file, $this->workspace->template, $base . '/' . $hostile . '/messages.po'],
                [$base . '/' . $hostile . '/messages.po'],
                null,
            ],
            'locale-unknown'       => $this->inLocaleNamed($hostile, $file),
            'plural-count-differs' => $this->withHostileParent($file, 3, null),
            'refused-reason'       => $this->refusedReason(),
            'no-plural-header'     => $this->withoutPluralRule(),
            'result-differs'       => $this->withHostileParent($file, 2, 's/msgstr "Hallo"/msgstr "Falsch"/'),
            'not-merged'           => $this->notMerged($file),
            'key-matches-no-entry' => [
                [$this->translations([$hostile => ['de' => 'x']]), $this->workspace->template, $valid],
                [$hostile],
                null,
            ],
            'locale-matches-no-catalogue' => [
                [$this->translations(['Hello' => [$hostile => 'x']]), $this->workspace->template, $valid],
                [$hostile],
                null,
            ],
            default => self::fail('Unknown scenario ' . $scenario),
        };
    }

    /**
     * Builds a catalogue whose directory is named like a locale that webtrees does not know, the hostile value.
     *
     * @param string $hostile The directory name
     * @param string $file    The translations file
     *
     * @return array{list<string>, list<string>, string|null}
     */
    private function inLocaleNamed(string $hostile, string $file): array
    {
        $catalogue = $this->prepare($this->hostileCatalogue($hostile), 2);

        return [[$file, $this->workspace->template, $catalogue], [$catalogue, $hostile], null];
    }

    /**
     * Builds a catalogue of the locale "de" below a hostile parent directory, so only its path carries the value.
     *
     * @param string      $file    The translations file
     * @param int         $plurals The number of plural forms the header declares
     * @param string|null $change  The change a misbehaving last tool makes, when the scenario needs one
     *
     * @return array{list<string>, list<string>, string|null}
     */
    private function withHostileParent(string $file, int $plurals, ?string $change): array
    {
        $catalogue = $this->prepare($this->hostileCatalogue('de'), $plurals);

        return [[$file, $this->workspace->template, $catalogue], [$catalogue], $change];
    }

    /**
     * Builds a catalogue whose source text is the hostile value and leaves its translation empty, so the filler
     * refuses the entry and the reason names the source text.
     *
     * @return array{list<string>, list<string>, string|null}
     */
    private function refusedReason(): array
    {
        $hostile = self::HOSTILE;
        $header  = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n"
            . "\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n\n";
        $entries = 'msgid "' . $hostile . "\"\nmsgstr \"\"\n";

        file_put_contents($this->workspace->template, $header . $entries);
        file_put_contents($this->workspace->catalogue, $header . $entries);
        GettextWorkspace::mergeFile($this->workspace->catalogue, $this->workspace->template);

        return [
            [$this->translations([$hostile => ['de' => '']]), $this->workspace->template, $this->workspace->catalogue],
            ['de: "' . $hostile . '" has an empty translation'],
            null,
        ];
    }

    /**
     * Builds a catalogue below a hostile parent directory whose header declares no plural rule while a plural entry
     * is to be written.
     *
     * @return array{list<string>, list<string>, string|null}
     */
    private function withoutPluralRule(): array
    {
        $catalogue = $this->hostileCatalogue('de');
        $header    = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n";
        $entries   = "msgid \"Hello\"\nmsgstr \"\"\n\nmsgid \"One\"\nmsgid_plural \"Many\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n";

        file_put_contents($this->workspace->template, $header . $entries);
        file_put_contents($catalogue, $header . $entries);
        GettextWorkspace::mergeFile($catalogue, $this->workspace->template);

        $file = $this->translations(['Hello' => ['de' => 'Hallo'], 'One' => ['de' => ['Eins', 'Viele']]]);

        return [[$file, $this->workspace->template, $catalogue], [$catalogue], null];
    }

    /**
     * Builds a catalogue below a hostile parent directory that is no longer what a merge with the template writes.
     *
     * @param string $file The translations file
     *
     * @return array{list<string>, list<string>, string|null}
     */
    private function notMerged(string $file): array
    {
        $catalogue = $this->prepare($this->hostileCatalogue('de'), 2);

        file_put_contents(
            $this->workspace->template,
            file_get_contents($this->workspace->template) . "\nmsgid \"Extra\"\nmsgstr \"\"\n",
        );

        return [[$file, $this->workspace->template, $catalogue], [$catalogue], null];
    }

    /**
     * Returns the path of a catalogue in a new directory that is named like the given locale and sits below a
     * directory named with the hostile value.
     *
     * @param string $locale The name of the directory that holds the catalogue
     *
     * @return string The path of the catalogue, the directory exists and the file does not
     */
    private function hostileCatalogue(string $locale): string
    {
        $directory = $this->workspace->emptyDirectory() . '/' . self::HOSTILE . '/' . $locale;

        mkdir($directory, 0700, true);

        return $directory . '/messages.po';
    }

    /**
     * Writes a translations file that holds a JSON string where an object is expected.
     *
     * @param string $path The path of the file
     *
     * @return string The path of the file
     */
    private function shapeless(string $path): string
    {
        file_put_contents($path, '"text"');

        return $path;
    }

    /**
     * Asserts that no line keeps a hostile value and that the cleaned text of every named value appears.
     *
     * @param list<string> $lines The lines of the report
     * @param list<string> $named The hostile values the report has to name in cleaned form
     */
    private function assertNamesOnlyTheCleanedText(array $lines, array $named): void
    {
        self::assertNotSame([], $lines, 'the command reports something');

        foreach ($lines as $line) {
            self::assertFalse(
                str_contains($line, '##[')
                || str_contains($line, "\u{202E}"),
                'a line of the report keeps a hostile value',
            );
        }

        foreach ($named as $value) {
            self::assertTrue(
                $this->anyLineContains($lines, PrintableText::line($value)),
                'the report names the cleaned text of a hostile value',
            );
        }
    }

    /**
     * Tells whether any line of the report contains the text.
     *
     * @param list<string> $lines The lines of the report
     * @param string       $text  The text to look for
     *
     * @return bool
     */
    private function anyLineContains(array $lines, string $text): bool
    {
        return array_filter($lines, static fn (string $line): bool => str_contains($line, $text)) !== [];
    }

    /**
     * Runs the command with a stub in front of msgmerge that runs the real last tool and then changes the text it
     * wrote, as a misbehaving tool would.
     *
     * @param string       $change    The sed expression with which the stub changes what the last tool wrote
     * @param list<string> $arguments The arguments of the command
     * @param list<string> $lines     The list the lines of the report are appended to
     *
     * @return int The exit status of the command
     */
    private function runWithChangedResult(string $change, array $arguments, array &$lines): int
    {
        $stub     = $this->workspace->emptyDirectory() . '/stub';
        $original = (string) getenv('PATH');

        mkdir($stub, 0700, true);
        file_put_contents(
            $stub . '/msgmerge',
            "#!/bin/sh\nPATH='" . $original . "' msgmerge \"\$@\" || exit \$?\n"
            . "case \" \$* \" in\n*' --update '*) ;;\n*) exit 0 ;;\nesac\n"
            . "for argument in \"\$@\"; do previous=\"\$last\"; last=\"\$argument\"; done\n"
            . "sed -i '" . $change . "' \"\$previous\"\n",
        );
        chmod($stub . '/msgmerge', 0700);
        putenv('PATH=' . $stub . ':' . $original);

        try {
            return (new WriteTranslationsCommand())->run($arguments, $this->collect($lines));
        } finally {
            putenv('PATH=' . $original);
        }
    }

    /**
     * Writes a template and a catalogue merged from it, both with two entries, one of them plural.
     *
     * @param string $path    The path of the catalogue
     * @param int    $plurals The number of plural forms the header declares
     *
     * @return string The path of the catalogue
     */
    private function prepare(string $path, int $plurals): string
    {
        return $this->workspace->prepareMergedCatalogue($path, $plurals);
    }

    /**
     * Writes a translations file.
     *
     * @param array<array-key, array<string, string|list<string>>> $translations The translations by key and locale
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
