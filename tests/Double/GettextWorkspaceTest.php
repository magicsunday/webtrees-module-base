<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Double;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_put_contents;
use function implode;

/**
 * The toolchain tests and the fuzzy resolver tests build their templates with the template
 * builder of the workspace double, so a defect there would hide a failure in them. This test
 * pins what the builder keeps and what it leaves out. The double is test code outside `src`,
 * so the class declares that it covers nothing.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversNothing]
final class GettextWorkspaceTest extends TestCase
{
    use SkipsWithoutGettextTools;

    /**
     * The header of a template, as the gettext tools write it on one line.
     */
    private const string TEMPLATE_HEADER = "msgid \"\"\nmsgstr \"Content-Type: text/plain; charset=UTF-8\\n\"\n\n";

    /**
     * Builds a catalogue text from the header followed by the given entries.
     *
     * @param list<string> $entries The entries after the header
     *
     * @return string The catalogue text
     */
    private function catalogue(array $entries): string
    {
        return GettextWorkspace::HEADER . implode("\n\n", $entries) . "\n";
    }

    /**
     * The template holds the source texts of every catalogue, once each and in the order they first
     * appear, with the translations emptied. A context and the plural form of a source text stay with
     * it, and the plural entry gets two empty forms.
     */
    #[Test]
    public function buildsOneEmptyEntryPerSourceTextOfAllCatalogues(): void
    {
        $this->requireTools();

        $workspace = new GettextWorkspace();

        try {
            $first = $workspace->addCatalogue('xa', $this->catalogue([
                "msgid \"Alpha\"\nmsgstr \"Alfa\"",
                "msgctxt \"menu\"\nmsgid \"File\"\nmsgstr \"Datei\"",
                "msgid \"One apple\"\nmsgid_plural \"%d apples\"\nmsgstr[0] \"Ein Apfel\"\nmsgstr[1] \"%d Äpfel\"",
            ]));
            $second = $workspace->addCatalogue('xb', $this->catalogue([
                "msgid \"Alpha\"\nmsgstr \"Alpha-b\"",
                "msgid \"Beta\"\nmsgstr \"Beta-b\"",
            ]));

            self::assertSame(
                self::TEMPLATE_HEADER
                . "msgid \"Alpha\"\nmsgstr \"\"\n\n"
                . "msgctxt \"menu\"\nmsgid \"File\"\nmsgstr \"\"\n\n"
                . "msgid \"One apple\"\nmsgid_plural \"%d apples\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n\n"
                . "msgid \"Beta\"\nmsgstr \"\"\n",
                GettextWorkspace::templateFrom([$first, $second]),
            );
        } finally {
            $workspace->remove();
        }
    }

    /**
     * An obsolete entry is no source text any more, so it does not reach the template.
     */
    #[Test]
    public function leavesObsoleteEntriesOutOfTheTemplate(): void
    {
        $this->requireTools();

        $workspace = new GettextWorkspace();

        try {
            $path = $workspace->addCatalogue('xa', $this->catalogue([
                "msgid \"Alpha\"\nmsgstr \"Alfa\"",
                "#~ msgid \"Gone\"\n#~ msgstr \"Weg\"",
            ]));

            self::assertSame(
                self::TEMPLATE_HEADER . "msgid \"Alpha\"\nmsgstr \"\"\n",
                GettextWorkspace::templateFrom([$path]),
            );
        } finally {
            $workspace->remove();
        }
    }

    /**
     * A source text that holds a line feed is written by the tools on several lines, and its entry stays in the
     * template like any other.
     */
    #[Test]
    public function keepsAnEntryWhoseSourceTextHoldsALineFeed(): void
    {
        $this->requireTools();

        $workspace = new GettextWorkspace();

        try {
            $path = $workspace->addCatalogue('xa', $this->catalogue([
                "msgid \"line one\\nline two\"\nmsgstr \"Zeile\"",
            ]));

            $template = GettextWorkspace::templateFrom([$path]);

            self::assertStringContainsString("\"line one\\n\"\n\"line two\"\nmsgstr \"\"", $template);
        } finally {
            $workspace->remove();
        }
    }

    /**
     * A translation that spans several lines is emptied like any other, for the singular and for every plural form.
     */
    #[Test]
    public function emptiesTranslationsThatSpanSeveralLines(): void
    {
        $this->requireTools();

        $workspace = new GettextWorkspace();

        try {
            $path = $workspace->addCatalogue('xa', $this->catalogue([
                "msgid \"a\"\nmsgstr \"x\\ny\"",
                "msgid \"p\"\nmsgid_plural \"q\"\nmsgstr[0] \"a\"\nmsgstr[1] \"b\\nc\"",
            ]));

            self::assertSame(
                self::TEMPLATE_HEADER
                . "msgid \"a\"\nmsgstr \"\"\n\n"
                . "msgid \"p\"\nmsgid_plural \"q\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n",
                GettextWorkspace::templateFrom([$path]),
            );
        } finally {
            $workspace->remove();
        }
    }

    /**
     * A catalogue the gettext tools cannot read adds nothing to the template, and the catalogues
     * after it still count.
     */
    #[Test]
    public function skipsACatalogueThatCannotBeRead(): void
    {
        $this->requireTools();

        $workspace = new GettextWorkspace();

        try {
            $readable = $workspace->addCatalogue('xa', $this->catalogue(["msgid \"Alpha\"\nmsgstr \"Alfa\""]));
            $broken   = $workspace->addCatalogue('xb', '');

            file_put_contents($broken, "msgid \"unterminated\nmsgstr \"\n");

            self::assertSame(
                self::TEMPLATE_HEADER . "msgid \"Alpha\"\nmsgstr \"\"\n",
                GettextWorkspace::templateFrom([$broken, $readable]),
            );
        } finally {
            $workspace->remove();
        }
    }

    /**
     * Every translations file lands in a directory of its own, so a test that makes the shared
     * scratch directory afterwards finds it free.
     */
    #[Test]
    public function writesEveryTranslationsFileIntoADirectoryOfItsOwn(): void
    {
        $workspace = new GettextWorkspace();

        try {
            $first  = $workspace->writeTranslations(['Hello' => ['de' => 'Hallo']]);
            $second = $workspace->writeTranslations(['Hello' => ['de' => 'Hallo']]);

            self::assertNotSame(dirname($first), dirname($second));
            self::assertFileExists($first);
            self::assertFileExists($second);
            self::assertDirectoryExists($workspace->emptyDirectory());
        } finally {
            $workspace->remove();
        }
    }
}
