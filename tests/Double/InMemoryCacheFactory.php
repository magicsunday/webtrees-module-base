<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Double;

use Fisharebest\Webtrees\Cache;
use Fisharebest\Webtrees\Contracts\CacheFactoryInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * A cache factory that keeps everything in memory for the lifetime of one instance.
 *
 * webtrees' real factory backs `file()` with a filesystem adapter rooted in the data
 * directory and a 100-day TTL, so a test using it writes entries that outlive the
 * process and are never read back. Code that memoises through `Registry::cache()`
 * therefore needs either a unique key per call — which leaks a file per assertion —
 * or a cache that simply forgets. This is the latter: construct one per test and the
 * isolation comes from its lifetime rather than from key hygiene.
 *
 * Both accessors share one adapter, mirroring the real factory's behaviour that a
 * value stored through one accessor is visible to code reading the same key.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class InMemoryCacheFactory implements CacheFactoryInterface
{
    /**
     * The shared in-memory backing store.
     */
    private ArrayAdapter $adapter;

    /**
     * Constructor.
     */
    public function __construct()
    {
        // storeSerialized=false keeps the stored values as-is; these caches only ever
        // hold scalars in tests, and skipping serialisation keeps failures readable.
        $this->adapter = new ArrayAdapter(0, false);
    }

    /**
     * Returns the in-memory cache.
     *
     * @return Cache
     */
    public function array(): Cache
    {
        return new Cache($this->adapter);
    }

    /**
     * Returns the same in-memory cache the array() accessor returns — nothing is
     * written to disk.
     *
     * @return Cache
     */
    public function file(): Cache
    {
        return new Cache($this->adapter);
    }
}
