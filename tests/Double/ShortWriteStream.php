<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Double;

use function intdiv;
use function strlen;

/**
 * A stream wrapper that creates directories and opens files but stores only half of what is written.
 *
 * It stands in for a full disk, which a test cannot cause on a real file system. Register it under a
 * protocol and hand a path of that protocol to the code under test.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class ShortWriteStream
{
    /**
     * The stream context PHP hands to a wrapper. It has to exist as a property.
     *
     * @var resource|null
     */
    public $context;

    /**
     * Accepts every directory.
     *
     * @return bool Always true
     */
    public function mkdir(): bool
    {
        return true;
    }

    /**
     * Accepts every file.
     *
     * @return bool Always true
     */
    public function stream_open(): bool
    {
        return true;
    }

    /**
     * Reports that only half of the data was stored.
     *
     * @param string $data The data to store
     *
     * @return int Half of the length of the data
     */
    public function stream_write(string $data): int
    {
        return intdiv(strlen($data), 2);
    }
}
