<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Support;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\DB;
use Fisharebest\Webtrees\Factories\CacheFactory;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\UserService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\Tree;
use MagicSunday\Webtrees\ModuleBase\Support\PendingRecords;
use MagicSunday\Webtrees\ModuleBase\Testing\RealTreeFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function count;
use function sort;
use function str_contains;

/**
 * Looks up the pending changes of records on a real schema. An editor sees pending changes, so only an
 * editor needs the lookup, and the lookup has to reach every xref of a list that is bound in chunks.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(PendingRecords::class)]
#[UsesClass(RealTreeFixture::class)]
final class PendingRecordsTest extends TestCase
{
    /**
     * Boots webtrees on a real schema.
     */
    protected function setUp(): void
    {
        parent::setUp();

        RealTreeFixture::boot();
    }

    /**
     * Drops the viewer and restores the minimal database of the other tests.
     */
    protected function tearDown(): void
    {
        Auth::logout();
        Session::clear();
        Site::$preferences = [];
        Registry::cache(new CacheFactory());

        RealTreeFixture::restoreMinimalDatabase();

        parent::tearDown();
    }

    /**
     * An editor gets the xrefs of the records with a pending change from every chunk of the list, as
     * strings, and not the records whose change is no longer pending.
     */
    #[Test]
    public function anEditorGetsThePendingXrefsOfEveryChunk(): void
    {
        $tree = RealTreeFixture::importGedcom("0 HEAD\n0 TRLR", 'pending');

        $this->insertChange($tree, '101', 'pending');
        $this->insertChange($tree, '103', 'pending');
        $this->insertChange($tree, '105', 'pending');
        $this->insertChange($tree, '104', 'accepted');

        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        $xrefs = PendingRecords::xrefs($tree, ['101', '102', '103', '104', '105'], 2);

        $log = $connection->getQueryLog();
        $connection->disableQueryLog();

        sort($xrefs);

        self::assertSame(['101', '103', '105'], $xrefs);

        $changeStatements = array_filter(
            $log,
            static fn (array $entry): bool => str_contains($entry['query'], '"wt_change"'),
        );

        // Five xrefs in chunks of two are three statements, and each one binds the tree, the status and
        // the xrefs of its chunk.
        self::assertCount(3, $changeStatements);

        foreach ($changeStatements as $entry) {
            self::assertLessThanOrEqual(4, count($entry['bindings']));
        }
    }

    /**
     * Only the changes of the given tree and of the given xrefs count.
     */
    #[Test]
    public function onlyTheGivenXrefsOfTheGivenTreeCount(): void
    {
        $tree  = RealTreeFixture::importGedcom("0 HEAD\n0 TRLR", 'pending');
        $other = RealTreeFixture::importGedcom("0 HEAD\n0 TRLR", 'other');

        $this->insertChange($tree, '101', 'pending');
        $this->insertChange($tree, '999', 'pending');
        $this->insertChange($other, '102', 'pending');

        self::assertSame(['101'], PendingRecords::xrefs($tree, ['101', '102'], 10));
    }

    /**
     * A visitor sees no pending change, so the lookup sends no statement for a visitor.
     */
    #[Test]
    public function aVisitorGetsNothingWithoutAStatement(): void
    {
        $tree = RealTreeFixture::importGedcom("0 HEAD\n0 TRLR", 'pending');

        $this->insertChange($tree, '101', 'pending');

        Auth::logout();

        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        $xrefs = PendingRecords::xrefs($tree, ['101'], 10);

        $changeStatements = array_filter(
            $connection->getQueryLog(),
            static fn (array $entry): bool => str_contains($entry['query'], '"wt_change"'),
        );
        $connection->disableQueryLog();

        self::assertSame([], $xrefs);
        self::assertSame([], $changeStatements);
    }

    /**
     * An empty list has nothing to look up, so the result is empty and no statement is sent, also for an
     * editor who could have pending changes.
     */
    #[Test]
    public function anEmptyListGetsNothingWithoutAStatement(): void
    {
        $tree = RealTreeFixture::importGedcom("0 HEAD\n0 TRLR", 'pending');

        $this->insertChange($tree, '101', 'pending');

        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        $xrefs = PendingRecords::xrefs($tree, [], 10);

        $changeStatements = array_filter(
            $connection->getQueryLog(),
            static fn (array $entry): bool => str_contains($entry['query'], '"wt_change"'),
        );

        $connection->disableQueryLog();

        self::assertSame([], $xrefs);
        self::assertSame([], $changeStatements);
    }

    /**
     * A member who is no editor sees no pending change either.
     */
    #[Test]
    public function aMemberWhoIsNoEditorGetsNothing(): void
    {
        $tree = RealTreeFixture::importGedcom("0 HEAD\n0 TRLR", 'pending');

        $this->insertChange($tree, '101', 'pending');

        $member = (new UserService())->create('member', 'Member', 'member@example.test', 'secret');
        $tree->setUserPreference($member, UserInterface::PREF_TREE_ROLE, UserInterface::ROLE_MEMBER);
        Auth::login($member);

        self::assertSame([], PendingRecords::xrefs($tree, ['101'], 10));
    }

    /**
     * Records a change of a record of the tree.
     *
     * @param Tree   $tree   The tree of the record
     * @param string $xref   The xref of the record
     * @param string $status The status of the change
     */
    private function insertChange(Tree $tree, string $xref, string $status): void
    {
        DB::table('change')->insert([
            'status'     => $status,
            'gedcom_id'  => $tree->id(),
            'xref'       => $xref,
            'old_gedcom' => '',
            'new_gedcom' => '0 @' . $xref . '@ OBJE',
            'user_id'    => 1,
        ]);
    }
}
