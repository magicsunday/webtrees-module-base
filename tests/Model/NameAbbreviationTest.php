<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Model;

use MagicSunday\Webtrees\ModuleBase\Model\NameAbbreviation;
use PHPUnit\Framework\Attributes\CoversClass;
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
#[CoversClass(NameAbbreviation::class)]
final class NameAbbreviationTest extends TestCase
{
    /**
     * @return array<string, array{NameAbbreviation, string, NameAbbreviation}>
     */
    public static function resolveDataProvider(): array
    {
        // [ configured strategy, tree's surname tradition, resolved strategy ]
        return [
            'Auto with icelandic tradition picks Surname' => [
                NameAbbreviation::Auto, 'icelandic', NameAbbreviation::Surname,
            ],
            'Auto with paternal tradition picks Given' => [
                NameAbbreviation::Auto, 'paternal', NameAbbreviation::Given,
            ],
            'Auto with empty tradition picks Given' => [
                NameAbbreviation::Auto, '', NameAbbreviation::Given,
            ],
            'Auto with unknown tradition picks Given' => [
                NameAbbreviation::Auto, 'something-new', NameAbbreviation::Given,
            ],
            'Given passes through regardless of tradition' => [
                NameAbbreviation::Given, 'icelandic', NameAbbreviation::Given,
            ],
            'Surname passes through regardless of tradition' => [
                NameAbbreviation::Surname, 'paternal', NameAbbreviation::Surname,
            ],
        ];
    }

    /**
     * Resolving maps the configured strategy against the tree's tradition, and
     * never yields Auto: the JS layer receives a concrete strategy.
     *
     * @param NameAbbreviation $configured
     * @param string           $surnameTradition
     * @param NameAbbreviation $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('resolveDataProvider')]
    public function resolveMapsConfigurationAgainstSurnameTradition(
        NameAbbreviation $configured,
        string $surnameTradition,
        NameAbbreviation $expected,
    ): void {
        self::assertSame($expected, $configured->resolve($surnameTradition));
        self::assertNotSame(NameAbbreviation::Auto, $configured->resolve($surnameTradition));
    }

    /**
     * @return array<string, array{string, NameAbbreviation|null}>
     */
    public static function storedValueDataProvider(): array
    {
        // [ value as persisted in module preferences, expected case ]
        return [
            'AUTO maps to Auto'              => ['AUTO', NameAbbreviation::Auto],
            'GIVEN maps to Given'            => ['GIVEN', NameAbbreviation::Given],
            'SURNAME maps to Surname'        => ['SURNAME', NameAbbreviation::Surname],
            'an unknown value is refused'    => ['nonsense', null],
            'the lookup is case-sensitive'   => ['auto', null],
            'an empty preference is refused' => ['', null],
        ];
    }

    /**
     * The backing values are what module preferences persist, so they are part of
     * the contract: a value stored by an earlier version must still map to the same
     * case, and anything else must be refused rather than silently accepted.
     *
     * Driven through a data provider on purpose — comparing a literal against the
     * case it defines is a tautology PHPStan resolves statically, and would stay
     * green if a case were renamed together with its value.
     *
     * @param string                $stored
     * @param NameAbbreviation|null $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('storedValueDataProvider')]
    public function storedValuesMapBackToTheirCase(string $stored, ?NameAbbreviation $expected): void
    {
        self::assertSame($expected, NameAbbreviation::tryFrom($stored));
    }
}
