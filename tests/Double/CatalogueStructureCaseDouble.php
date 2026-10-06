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
     * Returns the directory that holds the catalogues of all locales.
     */
    protected static function languageDirectory(): string
    {
        return self::$directory;
    }
}
