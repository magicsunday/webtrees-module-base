<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Double;

use MagicSunday\Webtrees\ModuleBase\Testing\AbstractCatalogueStructureTestCase;

/**
 * Concrete catalogue structure test case whose catalogue directory is set from outside, so
 * that the checks of the abstract case can be run against fixture catalogues.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class CatalogueStructureCaseDouble extends AbstractCatalogueStructureTestCase
{
    /**
     * Directory that holds the catalogues of all locales the checks run against.
     */
    public static string $directory = '';

    /**
     * Whether the double declares that its catalogues ship plural entries.
     */
    public static bool $shipsPluralEntries = true;

    /**
     * Returns the directory that holds the catalogues of all locales.
     *
     * @return string The path of the catalogue directory
     */
    protected static function languageDirectory(): string
    {
        return self::$directory;
    }

    /**
     * Returns whether the catalogues of the double are declared to ship plural entries.
     *
     * @return bool True when plural entries are expected
     */
    protected static function shipsPluralEntries(): bool
    {
        return self::$shipsPluralEntries;
    }
}
