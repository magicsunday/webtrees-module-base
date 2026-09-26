<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src/',
        __DIR__ . '/tests/',
    ]);

    if (
        !is_dir($concurrentDirectory = __DIR__ . '/.build/cache/.rector.cache')
        && !mkdir($concurrentDirectory, 0775, true)
        && !is_dir($concurrentDirectory)
    ) {
        throw new \RuntimeException(
            sprintf(
                'Directory "%s" was not created',
                $concurrentDirectory
            )
        );
    }

    if (
        !is_dir($concurrentDirectory = __DIR__ . '/.build/cache/.rector.container.cache')
        && !mkdir($concurrentDirectory, 0775, true)
        && !is_dir($concurrentDirectory)
    ) {
        throw new \RuntimeException(
            sprintf(
                'Directory "%s" was not created',
                $concurrentDirectory
            )
        );
    }

    $rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon');
    $rectorConfig->cacheDirectory(__DIR__ . '/.build/cache/.rector.cache');
    $rectorConfig->containerCacheDirectory(__DIR__ . '/.build/cache/.rector.container.cache');

    // The shared rule sets and skips; 80300 is this package's PHP floor.
    (require __DIR__ . '/.build/vendor/magicsunday/coding-standard/rector/base.php')($rectorConfig, 80300);
};
