<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Model;

use MagicSunday\Webtrees\ModuleBase\Model\FactView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Pins the serialised form of {@see FactView}, which the front end reads.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(FactView::class)]
final class FactViewTest extends TestCase
{
    /**
     * The serialised fact carries the six values under fixed keys, in constructor order.
     */
    #[Test]
    public function serialisesTheSixValuesInConstructorOrder(): void
    {
        $fact = new FactView(
            tag: 'CHR',
            label: 'Christening',
            date: '12.05.1832',
            place: 'Berlin, Germany',
            placeFull: 'Charlottenburg, Berlin, Brandenburg, Germany',
            value: 'Parish register',
        );

        self::assertSame(
            ['tag', 'label', 'date', 'place', 'placeFull', 'value'],
            array_keys($fact->jsonSerialize())
        );

        self::assertSame(
            '{"tag":"CHR","label":"Christening","date":"12.05.1832","place":"Berlin, Germany",'
            . '"placeFull":"Charlottenburg, Berlin, Brandenburg, Germany","value":"Parish register"}',
            json_encode($fact, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Empty strings stay empty strings, so a consumer can tell a missing part by its length.
     */
    #[Test]
    public function keepsEmptyPartsAsEmptyStrings(): void
    {
        $fact = new FactView(
            tag: 'OCCU',
            label: 'Occupation',
            date: '',
            place: '',
            placeFull: '',
            value: 'Smith',
        );

        self::assertSame(
            [
                'tag'       => 'OCCU',
                'label'     => 'Occupation',
                'date'      => '',
                'place'     => '',
                'placeFull' => '',
                'value'     => 'Smith',
            ],
            $fact->jsonSerialize()
        );
    }
}
