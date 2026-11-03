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
use MagicSunday\Webtrees\ModuleBase\Testing\ModuleSettingsFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pins what the module settings fixture writes into the `module_setting` table.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(ModuleSettingsFixture::class)]
final class ModuleSettingsFixtureTest extends TestCase
{
    /**
     * The seeded preferences are stored under the name of the module, one row for each.
     *
     * @return void
     */
    #[Test]
    public function storesEachPreferenceUnderTheNameOfTheModule(): void
    {
        ModuleSettingsFixture::seed('the-module', ['generations' => '4', 'layout' => 'down']);

        self::assertSame(['the-module'], DB::table('module_setting')->distinct()->pluck('module_name')->all());
        self::assertSame(
            ['generations' => '4', 'layout' => 'down'],
            DB::table('module_setting')->orderBy('setting_name')->pluck('setting_value', 'setting_name')->all()
        );
    }

    /**
     * A later seed replaces every row of the earlier one, also those of another module.
     *
     * @return void
     */
    #[Test]
    public function aLaterSeedReplacesTheRowsOfTheEarlierOne(): void
    {
        ModuleSettingsFixture::seed('first-module', ['generations' => '4']);
        ModuleSettingsFixture::seed('second-module', ['layout' => 'up']);

        self::assertSame(['second-module'], DB::table('module_setting')->distinct()->pluck('module_name')->all());
        self::assertSame(['layout' => 'up'], DB::table('module_setting')->pluck('setting_value', 'setting_name')->all());
    }

    /**
     * Seeding no preferences leaves an empty table.
     *
     * @return void
     */
    #[Test]
    public function seedingNoPreferencesLeavesAnEmptyTable(): void
    {
        ModuleSettingsFixture::seed('the-module', ['generations' => '4']);
        ModuleSettingsFixture::seed('the-module', []);

        self::assertSame(0, DB::table('module_setting')->count());
    }
}
