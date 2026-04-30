<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use MagicSunday\Webtrees\ModuleBase\Model\NameAbbreviation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * NameAbbreviationTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
class NameAbbreviationTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function resolveDataProvider(): array
    {
        return [
            'AUTO with icelandic tradition picks SURNAME' => [
                NameAbbreviation::AUTO, 'icelandic', NameAbbreviation::SURNAME,
            ],
            'AUTO with paternal tradition picks GIVEN' => [
                NameAbbreviation::AUTO, 'paternal', NameAbbreviation::GIVEN,
            ],
            'AUTO with empty tradition picks GIVEN' => [
                NameAbbreviation::AUTO, '', NameAbbreviation::GIVEN,
            ],
            'AUTO with unknown tradition picks GIVEN' => [
                NameAbbreviation::AUTO, 'something-new', NameAbbreviation::GIVEN,
            ],
            'GIVEN passes through regardless of tradition' => [
                NameAbbreviation::GIVEN, 'icelandic', NameAbbreviation::GIVEN,
            ],
            'SURNAME passes through regardless of tradition' => [
                NameAbbreviation::SURNAME, 'paternal', NameAbbreviation::SURNAME,
            ],
        ];
    }

    #[Test]
    #[DataProvider('resolveDataProvider')]
    public function resolveMapsConfigurationAgainstSurnameTradition(
        string $configured,
        string $surnameTradition,
        string $expected,
    ): void {
        self::assertSame(
            $expected,
            NameAbbreviation::resolve($configured, $surnameTradition)
        );
    }
}
