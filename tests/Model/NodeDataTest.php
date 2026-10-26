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
use MagicSunday\Webtrees\ModuleBase\Model\FactView;
use MagicSunday\Webtrees\ModuleBase\Model\NameSegment;
use MagicSunday\Webtrees\ModuleBase\Model\NameSegmentKind;
use MagicSunday\Webtrees\ModuleBase\Model\NodeData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function substr;

use const JSON_THROW_ON_ERROR;

/**
 * Pins the serialised shape of {@see NodeData}. The JSON contract to the
 * front end is part of the public surface. The chart modules read these keys
 * in this order, so a change here is a deliberate contract change.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(NodeData::class)]
#[UsesClass(FactView::class)]
#[UsesClass(NameSegment::class)]
#[UsesClass(NameSegmentKind::class)]
final class NodeDataTest extends TestCase
{
    /**
     * The serialised form of a node that has not been filled in.
     */
    private const string EMPTY_JSON
        = '{"id":0,"xref":"","url":"","updateUrl":"","generation":0,"name":"","isNameRtl":false,'
        . '"nameSegments":[],"alternativeName":"",'
        . '"isAltRtl":false,"thumbnail":"","silhouette":"","sex":"U","birth":"","death":"","timespan":""}';

    /**
     * The serialised form of a fully populated node.
     */
    private const string FILLED_JSON
        = '{"id":7,"xref":"X1","url":"\/individual\/X1","updateUrl":"\/chart\/X1","generation":3,'
        . '"name":"Max Mustermann","isNameRtl":true,"nameSegments":['
        . '{"label":"Max","kind":"given","isPreferred":true},{"label":"\"M\u00e4xchen\"","kind":"nickname","isPreferred":false},'
        . '{"label":"Mustermann","kind":"surname","isPreferred":false}],"alternativeName":"Maxi Muster","isAltRtl":true,'
        . '"thumbnail":"\/media\/x.jpg","silhouette":"\/media\/m.svg","sex":"M","birth":"1 JAN 1900",'
        . '"death":"2 FEB 1980","timespan":"1900 - 1980"}';

    /**
     * A node that has not been filled in serialises every key, in the fixed order.
     *
     * @return void
     */
    #[Test]
    public function serialisesAnEmptyNodeInTheFixedKeyOrder(): void
    {
        self::assertSame(self::EMPTY_JSON, json_encode(new NodeData(), JSON_THROW_ON_ERROR));
    }

    /**
     * A filled node serialises its values and its name segments in the fixed order.
     *
     * @return void
     */
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
            ->setNameSegments([
                new NameSegment('Max', NameSegmentKind::Given, true),
                new NameSegment('"Mäxchen"', NameSegmentKind::Nickname),
                new NameSegment('Mustermann', NameSegmentKind::Surname),
            ])
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

    /**
     * The individual the node was built from never reaches the front end.
     *
     * @return void
     */
    #[Test]
    public function keepsTheUnderlyingIndividualOutOfTheSerialisedForm(): void
    {
        $nodeData = (new NodeData())->setIndividual(self::createStub(Individual::class));

        self::assertSame(self::EMPTY_JSON, json_encode($nodeData, JSON_THROW_ON_ERROR));
    }

    /**
     * The facts of a node are appended as the last key, and a missing slot stays a null entry, so
     * a front end that reserves one row per slot keeps its alignment.
     *
     * @return void
     */
    #[Test]
    public function appendsTheFactsAsTheLastKeyWhenTheyAreSet(): void
    {
        $nodeData = (new NodeData())->setFacts([
            new FactView(
                tag: 'BIRT',
                label: 'Birth',
                date: '12.05.1832',
                place: 'Berlin',
                placeFull: 'Berlin, Germany',
                value: '',
            ),
            null,
        ]);

        $json = json_encode($nodeData, JSON_THROW_ON_ERROR);

        self::assertSame(
            substr(self::EMPTY_JSON, 0, -1)
            . ',"facts":[{"tag":"BIRT","label":"Birth","date":"12.05.1832","place":"Berlin",'
            . '"placeFull":"Berlin, Germany","value":"","partner":"","sortDay":0},null]}',
            $json
        );
    }

    /**
     * A list of slots that are all empty is still a deliberate answer and is serialised, while a node
     * whose facts were never set or were set to an empty list keeps its payload unchanged.
     *
     * @return void
     */
    #[Test]
    public function serialisesAListOfEmptySlotsButOmitsAnEmptyList(): void
    {
        self::assertSame(
            substr(self::EMPTY_JSON, 0, -1) . ',"facts":[null,null]}',
            json_encode((new NodeData())->setFacts([null, null]), JSON_THROW_ON_ERROR)
        );

        self::assertSame(self::EMPTY_JSON, json_encode((new NodeData())->setFacts([]), JSON_THROW_ON_ERROR));
        self::assertSame(self::EMPTY_JSON, json_encode(new NodeData(), JSON_THROW_ON_ERROR));
    }

    /**
     * Setting the facts again replaces the earlier list instead of extending it, and an empty list
     * clears the facts of a node that had some.
     *
     * @return void
     */
    #[Test]
    public function aSecondSetOfFactsReplacesTheFirst(): void
    {
        $nodeData = (new NodeData())->setFacts([null, null]);

        self::assertSame(
            substr(self::EMPTY_JSON, 0, -1) . ',"facts":[null]}',
            json_encode($nodeData->setFacts([null]), JSON_THROW_ON_ERROR)
        );

        self::assertSame(self::EMPTY_JSON, json_encode($nodeData->setFacts([]), JSON_THROW_ON_ERROR));
    }
}
