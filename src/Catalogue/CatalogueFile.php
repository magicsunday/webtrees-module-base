<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Catalogue;

use function is_file;
use function is_link;
use function is_readable;

/**
 * Tells why a file named on the command line cannot be used, before a tool looks at it.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class CatalogueFile
{
    /**
     * Static-only class, it is never instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Tells why a file cannot be read.
     *
     * @param string $path The path of the file
     *
     * @return string|null The rest of the sentence that names the file, null when it can be read
     */
    public static function unreadable(string $path): ?string
    {
        return match (true) {
            !is_file($path)     => 'not found',
            !is_readable($path) => 'is not readable',
            default             => null,
        };
    }

    /**
     * Tells why a catalogue cannot be processed. A catalogue that is a link is refused, because the new file would
     * replace the link.
     *
     * @param string $catalogue The path of the catalogue
     *
     * @return string|null The rest of the sentence that names the catalogue, null when it can be processed
     */
    public static function unusable(string $catalogue): ?string
    {
        return self::unreadable($catalogue)
            ?? (is_link($catalogue) ? 'is a link, name the file it points to' : null);
    }
}
