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
    /**
     * The header every catalogue and every template starts with.
     */
    private const string HEADER = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n";

    /**
     * Builds a catalogue text from the header followed by the given entries.
     *
     * @param list<string> $entries The entries after the header
     *
     * @return string The catalogue text
     */
    private function catalogue(array $entries): string
    {
        return self::HEADER . implode("\n\n", $entries) . "\n";
    }

    /**
     * Skips the test where the gettext tools are missing, as the other catalogue tests do.
     *
     * @return void
     */
    private function requireTools(): void
    {
        if (!GettextWorkspace::toolsAvailable()) {
            self::markTestSkipped('The gettext tools are not installed.');
        }
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
                self::HEADER
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
                self::HEADER . "msgid \"Alpha\"\nmsgstr \"\"\n",
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
                self::HEADER . "msgid \"Alpha\"\nmsgstr \"\"\n",
                GettextWorkspace::templateFrom([$broken, $readable]),
            );
        } finally {
            $workspace->remove();
        }
    }
}
