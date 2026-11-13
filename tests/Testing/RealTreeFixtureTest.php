<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Testing;

use Aura\Router\RouterContainer;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\GuestUser;
use Fisharebest\Webtrees\Http\RequestHandlers\HomePage;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Site;
use MagicSunday\Webtrees\ModuleBase\Testing\RealTreeFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

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
     * Code that builds a link or reads the request finds the routes of webtrees and a web request of a
     * guest from the test host in the container.
     */
    #[Test]
    public function registersTheRoutesAndTheRequestOfAWebRequest(): void
    {
        RealTreeFixture::boot();

        $router  = Registry::container()->get(RouterContainer::class);
        $request = Registry::container()->get(ServerRequestInterface::class);

        self::assertInstanceOf(RouterContainer::class, $router);
        self::assertStringStartsWith('/', $router->getGenerator()->generate(HomePage::class));

        self::assertInstanceOf(ServerRequestInterface::class, $request);
        self::assertSame('https://webtrees.test', $request->getAttribute('base_url'));
        self::assertSame('127.0.0.1', $request->getAttribute('client-ip'));
        self::assertInstanceOf(GuestUser::class, $request->getAttribute('user'));
    }

    /**
     * The restore drops the administrator that boot() logged in, because the user table of the real schema
     * is gone and a later test would otherwise run as a user that no longer exists.
     */
    #[Test]
    public function restoreDropsTheAdministratorThatBootLoggedIn(): void
    {
        RealTreeFixture::boot();

        self::assertTrue(Auth::isAdmin());

        RealTreeFixture::restoreMinimalDatabase();

        self::assertNull(Auth::id());
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
