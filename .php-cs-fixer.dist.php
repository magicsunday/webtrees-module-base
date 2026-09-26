<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    die('This script supports command line usage only. Please check your command.');
}

// The shared ruleset lives in magicsunday/coding-standard; this file only
// supplies the package's own file header and its finder.
$factory = require __DIR__ . '/.build/vendor/magicsunday/coding-standard/php-cs-fixer/base.php';

return $factory(<<<EOF
    This file is part of the package magicsunday/webtrees-module-base.

    For the full copyright and license information, please read the
    LICENSE file that was distributed with this source code.
    EOF)
    ->setCacheFile(__DIR__ . '/.build/cache/.php-cs-fixer.cache')
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude([
                '.build',
                'node_modules',
            ])
            ->in([
                __DIR__ . '/src/',
                __DIR__ . '/tests/',
            ])
    );
