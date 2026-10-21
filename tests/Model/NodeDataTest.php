<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Model;

use Fisharebest\Webtrees\Individual;
use MagicSunday\Webtrees\ModuleBase\Model\NodeData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Pins the serialised shape of {@see NodeData}. The JSON contract to the
 * front end is part of the public surface: the chart modules read these keys
 * in this order, so a change here is a deliberate contract change.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(NodeData::class)]
final class NodeDataTest extends TestCase
{
    /**
     * The serialised form of a node that has not been filled in.
     */
    private const string EMPTY_JSON
        = '{"id":0,"xref":"","url":"","updateUrl":"","generation":0,"name":"","isNameRtl":false,'
        . '"firstNames":[],"lastNames":[],"preferredName":"","nickname":"","alternativeName":"",'
        . '"isAltRtl":false,"thumbnail":"","silhouette":"","sex":"U","birth":"","death":"","timespan":""}';

    /**
     * The serialised form of a fully populated node.
     */
    private const string FILLED_JSON
        = '{"id":7,"xref":"X1","url":"\/individual\/X1","updateUrl":"\/chart\/X1","generation":3,'
        . '"name":"Max Mustermann","isNameRtl":true,"firstNames":["Max","Peter"],"lastNames":["Mustermann"],'
        . '"preferredName":"Max","nickname":"M\u00e4xchen","alternativeName":"Maxi Muster","isAltRtl":true,'
        . '"thumbnail":"\/media\/x.jpg","silhouette":"\/media\/m.svg","sex":"M","birth":"1 JAN 1900",'
        . '"death":"2 FEB 1980","timespan":"1900 - 1980"}';

    #[Test]
    public function serialisesAnEmptyNodeInTheFixedKeyOrder(): void
    {
        self::assertSame(self::EMPTY_JSON, json_encode(new NodeData(), JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function serialisesAFilledNodeInTheFixedKeyOrder(): void
    {
        $nodeData = (new NodeData())
            ->setId(7)
            ->setXref('X1')
            ->setUrl('/individual/X1')
            ->setUpdateUrl('/chart/X1')
            ->setGeneration(3)
            ->setName('Max Mustermann')
            ->setIsNameRtl(true)
            ->setFirstNames(['Max', 'Peter'])
            ->setLastNames(['Mustermann'])
            ->setPreferredName('Max')
            ->setNickname('Mäxchen')
            ->setAlternativeName('Maxi Muster')
            ->setIsAltRtl(true)
            ->setThumbnail('/media/x.jpg')
            ->setSilhouette('/media/m.svg')
            ->setSex('M')
            ->setBirth('1 JAN 1900')
            ->setDeath('2 FEB 1980')
            ->setTimespan('1900 - 1980');

        self::assertSame(self::FILLED_JSON, json_encode($nodeData, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function keepsTheUnderlyingIndividualOutOfTheSerialisedForm(): void
    {
        $nodeData = (new NodeData())->setIndividual(self::createStub(Individual::class));

        self::assertSame(self::EMPTY_JSON, json_encode($nodeData, JSON_THROW_ON_ERROR));
    }
}
