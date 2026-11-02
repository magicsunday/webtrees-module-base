<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Testing;

use Fisharebest\Webtrees\DB;
use Illuminate\Database\Schema\Blueprint;

use function array_keys;
use function array_map;
use function array_values;

/**
 * Seeds the preferences of a module into a real in-memory database.
 *
 * The method that reads a preference of a module is final and cannot be stubbed, and the real
 * implementation reads from the `module_setting` table, so the tests that need preferences boot a
 * minimal SQLite schema for it. A module uses the fixture in the tests of its own suite and builds
 * its module object itself. The fixture replaces the global database connection of the process by
 * the in-memory one, so it is meant for tests only.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class ModuleSettingsFixture
{
    /**
     * Whether the in-memory database with the `module_setting` table is booted in this process.
     */
    private static bool $booted = false;

    /**
     * Prevents instances of the fixture, because its state is the database of the process.
     */
    private function __construct()
    {
    }

    /**
     * Replaces the preferences of every module by the given preferences of one module.
     *
     * @param string                $moduleName  The name under which the module is registered
     * @param array<string, string> $preferences The preferences to seed, keyed by setting name
     */
    public static function seed(string $moduleName, array $preferences): void
    {
        self::boot();

        DB::table('module_setting')->delete();
        DB::table('module_setting')->insert(
            array_map(
                static fn (string $name, string $value): array => [
                    'module_name'   => $moduleName,
                    'setting_name'  => $name,
                    'setting_value' => $value,
                ],
                array_keys($preferences),
                array_values($preferences),
            )
        );
    }

    /**
     * Boots the in-memory SQLite database with a minimal `module_setting` schema once per process.
     */
    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        $database = new DB();
        $database->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
        $database->setAsGlobal();
        $database->bootEloquent();
        DB::connection()->getSchemaBuilder()->create('module_setting', static function (Blueprint $table): void {
            $table->string('module_name');
            $table->string('setting_name');
            $table->string('setting_value');
        });

        self::$booted = true;
    }
}
