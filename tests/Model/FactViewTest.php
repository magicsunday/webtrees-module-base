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
     * The serialised fact carries its values under fixed keys, in constructor order.
     */
    #[Test]
    public function serialisesTheValuesInConstructorOrder(): void
    {
        $fact = new FactView(
            tag: 'CHR',
            label: 'Christening',
            date: '12.05.1832',
            place: 'Berlin, Germany',
            placeFull: 'Charlottenburg, Berlin, Brandenburg, Germany',
            value: 'Parish register',
            partner: 'Anna Schmidt',
            sortDay: 2390316,
        );

        self::assertSame(
            ['tag', 'label', 'date', 'place', 'placeFull', 'value', 'partner', 'sortDay'],
            array_keys($fact->jsonSerialize())
        );

        self::assertSame(
            '{"tag":"CHR","label":"Christening","date":"12.05.1832","place":"Berlin, Germany",'
            . '"placeFull":"Charlottenburg, Berlin, Brandenburg, Germany","value":"Parish register",'
            . '"partner":"Anna Schmidt","sortDay":2390316}',
            json_encode($fact, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * A fact without a usable date carries the sort day zero, so the key is always there.
     */
    #[Test]
    public function carriesTheSortDayZeroByDefault(): void
    {
        $fact = new FactView(tag: 'BIRT', label: 'Birth', date: '', place: '', placeFull: '', value: '');

        self::assertSame(0, $fact->sortDay);
        self::assertSame(0, $fact->jsonSerialize()['sortDay']);
    }

    /**
     * A fact without a partner carries an empty partner, so the key is always there.
     */
    #[Test]
    public function carriesAnEmptyPartnerByDefault(): void
    {
        $fact = new FactView(tag: 'BIRT', label: 'Birth', date: '', place: '', placeFull: '', value: '');

        self::assertSame('', $fact->partner);
        self::assertSame('', $fact->jsonSerialize()['partner']);
    }
}
