<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Testing;

use Fisharebest\Webtrees\DB;
use LogicException;
use MagicSunday\Webtrees\ModuleBase\Testing\ModuleSettingsFixture;
use MagicSunday\Webtrees\ModuleBase\Testing\RealTreeFixture;
use MagicSunday\Webtrees\ModuleBase\Testing\StatementLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the statements that an action sends are returned in the order they were sent, with
 * their bindings as text, and that the log of the connection is off again afterwards.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(StatementLog::class)]
#[UsesClass(RealTreeFixture::class)]
#[UsesClass(ModuleSettingsFixture::class)]
final class StatementLogTest extends TestCase
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
     * Restores the minimal database of the other tests.
     */
    protected function tearDown(): void
    {
        RealTreeFixture::restoreMinimalDatabase();

        parent::tearDown();
    }

    /**
     * The statements of the action come back in the order they were sent, with the bindings of each as
     * text, so the numbers that the action binds can be compared with a text.
     */
    #[Test]
    public function theStatementsOfTheActionComeBackInOrderWithTextBindings(): void
    {
        $statements = StatementLog::capture(static function (): void {
            DB::table('gedcom')->where('gedcom_id', '=', 7)->get();
            DB::table('user')->where('user_id', '=', 'x')->get();
        });

        self::assertCount(2, $statements);
        self::assertStringContainsString('from "wt_gedcom"', $statements[0]['query']);
        self::assertSame(['7'], $statements[0]['bindings']);
        self::assertStringContainsString('from "wt_user"', $statements[1]['query']);
        self::assertSame(['x'], $statements[1]['bindings']);
    }

    /**
     * A statement that was sent before the action, while the log was already on, is not part of the
     * result, so a count of the action does not depend on what the test did earlier.
     */
    #[Test]
    public function aStatementOfAnEarlierCallIsNotPartOfTheResult(): void
    {
        DB::connection()->enableQueryLog();
        DB::table('gedcom')->get();

        $statements = StatementLog::capture(static function (): void {
            DB::table('user')->get();
        });

        self::assertCount(1, $statements);
        self::assertStringContainsString('from "wt_user"', $statements[0]['query']);
    }

    /**
     * The log of the connection is off after the capture, so the statements of a later call are not
     * collected and a long test run does not grow the log.
     */
    #[Test]
    public function theLogIsOffAfterTheCapture(): void
    {
        StatementLog::capture(static function (): void {
        });

        DB::table('gedcom')->get();

        self::assertCount(0, DB::connection()->getQueryLog());
    }

    /**
     * An action that throws leaves the log off as well, so the statements of a later part of the test are
     * not collected on the shared connection.
     */
    #[Test]
    public function theLogIsOffAfterAnActionThatThrows(): void
    {
        try {
            StatementLog::capture(static function (): never {
                throw new LogicException('The action fails.');
            });
        } catch (LogicException) {
            // The failure of the action is the case under test.
        }

        DB::table('gedcom')->get();

        self::assertCount(0, DB::connection()->getQueryLog());
    }

    /**
     * A binding that is no scalar becomes an empty text and keeps its position, so the bindings of a
     * statement stay aligned with the placeholders.
     */
    #[Test]
    public function aBindingThatIsNoScalarBecomesAnEmptyText(): void
    {
        $statements = StatementLog::capture(static function (): void {
            DB::connection()->select('select ? as first, ? as second, ? as third', [1, null, 'x']);
        });

        self::assertCount(1, $statements);
        self::assertSame(['1', '', 'x'], $statements[0]['bindings']);
    }

    /**
     * An action without a statement gives an empty list.
     */
    #[Test]
    public function anActionWithoutStatementsGivesAnEmptyList(): void
    {
        self::assertSame([], StatementLog::capture(static function (): void {
        }));
    }
}
