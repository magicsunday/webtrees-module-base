<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Testing;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Site;
use MagicSunday\Webtrees\ModuleBase\Testing\RealTreeFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pins what the real-tree fixture gives a test, which is a booted schema with an administrator, a tree
 * that holds the imported records, and a minimal database again afterwards.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(RealTreeFixture::class)]
final class RealTreeFixtureTest extends TestCase
{
    /**
     * Drops the viewer and restores the minimal database for the other tests of the process.
     */
    protected function tearDown(): void
    {
        Auth::logout();
        Session::clear();
        Site::$preferences = [];

        RealTreeFixture::restoreMinimalDatabase();

        parent::tearDown();
    }

    /**
     * The imported records are the records of the new tree, read through the real record factories, and
     * the viewer is an administrator.
     */
    #[Test]
    public function importsTheRecordsOfAGedcomIntoANewTree(): void
    {
        RealTreeFixture::boot();

        $tree = RealTreeFixture::importGedcom(
            "0 HEAD\n0 @1@ INDI\n1 NAME Ada /Lovelace/\n0 TRLR",
            'fixture',
        );

        $person = Registry::individualFactory()->make('1', $tree);

        self::assertInstanceOf(Individual::class, $person);
        self::assertSame('fixture', $tree->name());
        self::assertTrue(Auth::isAdmin());
        self::assertTrue($person->canShow());
    }

    /**
     * After the restore the database holds the module settings only, like the one that the settings
     * fixture boots, and no table of the real schema is left.
     */
    #[Test]
    public function restoresTheMinimalDatabase(): void
    {
        RealTreeFixture::boot();
        RealTreeFixture::restoreMinimalDatabase();

        $schema = DB::connection()->getSchemaBuilder();

        self::assertTrue($schema->hasTable('module_setting'));
        self::assertFalse($schema->hasTable('individuals'));
    }
}
