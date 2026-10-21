<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Model;

use MagicSunday\Webtrees\ModuleBase\Model\NameSegment;
use MagicSunday\Webtrees\ModuleBase\Model\NameSegmentKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Pins the serialised form of {@see NameSegment}, which the front end reads.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(NameSegment::class)]
#[UsesClass(NameSegmentKind::class)]
final class NameSegmentTest extends TestCase
{
    /**
     * @return array<string, array{NameSegment, string}>
     */
    public static function segmentDataProvider(): array
    {
        // One case per kind, the preferred flag, and the escaping of quotes and non-ASCII letters
        return [
            'given name' => [
                new NameSegment('Max', NameSegmentKind::Given),
                '{"label":"Max","kind":"given","isPreferred":false}',
            ],
            'preferred given name' => [
                new NameSegment('Peter', NameSegmentKind::Given, true),
                '{"label":"Peter","kind":"given","isPreferred":true}',
            ],
            'surname' => [
                new NameSegment('Mustermann', NameSegmentKind::Surname),
                '{"label":"Mustermann","kind":"surname","isPreferred":false}',
            ],
            'nickname' => [
                new NameSegment('"Fritz"', NameSegmentKind::Nickname),
                '{"label":"\"Fritz\"","kind":"nickname","isPreferred":false}',
            ],
            'married surname' => [
                new NameSegment('(Müller)', NameSegmentKind::Married),
                '{"label":"(M\u00fcller)","kind":"married","isPreferred":false}',
            ],
        ];
    }

    /**
     * The label is kept as given and the kind is written as its backing value.
     *
     * @param NameSegment $segment
     * @param string      $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('segmentDataProvider')]
    public function serialisesWithTheKindAsItsValue(NameSegment $segment, string $expected): void
    {
        self::assertSame($expected, json_encode($segment, JSON_THROW_ON_ERROR));
    }
}
