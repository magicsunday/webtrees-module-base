<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use DOMXPath;
use MagicSunday\Webtrees\ModuleBase\Processor\NameProcessor;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * NameProcessorTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
class NameProcessorTest extends TestCase
{
    /**
     * @return string[][]
     */
    public function convertToHtmlEntitiesDataProvider(): array
    {
        // [ input, expected ]
        return [
            // German umlauts
            [
                '<div>abc <span>äöü</span> <p>&#228;&#246;&#252;</p></div>',
                '<div>abc <span>&#228;&#246;&#252;</span> <p>&#228;&#246;&#252;</p></div>',
            ],
            [
                '<div>abc <span>&auml;&ouml;&uuml;</span> <p>&#228;&#246;&#252;</p></div>',
                '<div>abc <span>&auml;&ouml;&uuml;</span> <p>&#228;&#246;&#252;</p></div>',
            ],

            // Euro sign
            [
                '€ &euro; &#8364;',
                '&#8364; &euro; &#8364;',
            ],

            // Korean
            [
                '박성욱',
                '&#48149;&#49457;&#50865;',
            ],
            [
                '<span><span>&#48149;</span>&#49457;&#50865;</span>',
                '<span><span>&#48149;</span>&#49457;&#50865;</span>',
            ],
        ];
    }

    /**
     * Tests conversion of UTF-8 characters to HTML entities.
     *
     * @test
     * @dataProvider convertToHtmlEntitiesDataProvider
     *
     * @param string $input
     * @param string $expected
     *
     * @return void
     */
    public function convertToHtmlEntities(string $input, string $expected): void
    {
        $reflectionClass   = new ReflectionClass(NameProcessor::class);
        $nameProcessorMock = $this->createMock(NameProcessor::class);

        $reflectionMethod = $reflectionClass->getMethod('convertToHtmlEntities');
        $reflectionMethod->setAccessible(true);

        $result = $reflectionMethod->invokeArgs($nameProcessorMock, [ $input ]);

        self::assertSame($expected, $result);
    }

    /**
     * @return string[][]
     */
    public function individualNameDataProvider(): array
    {
        // [ input, expected ]
        return [
            [
                '<span class="NAME" dir="auto" translate="no"><span class="starredname">Max</span> Hermann <span class="SURN">Mustermann</span></span>',
                [
                    [
                        'Max',
                        'Hermann',
                    ],
                    [
                        'Mustermann',
                    ],
                    [
                        'Max',
                    ],
                ],
            ],

            [
                '<span class="NAME" dir="auto" translate="no">Max Hermann <span class="SURN">Mustermann</span></span>',
                [
                    [
                        'Max',
                        'Hermann',
                    ],
                    [
                        'Mustermann',
                    ],
                    [
                        '',
                    ],
                ],
            ],

            [
                '<span class="NAME" dir="auto" translate="no">José <span class="starredname">Antonio</span> <span class="SURN">Gómez</span> <span class="SURN">Iglesias</span></span>',
                [
                    [
                        'José',
                        'Antonio',
                    ],
                    [
                        'Gómez',
                        'Iglesias',
                    ],
                    [
                        'Antonio',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param string|string[] $expected
     * @param string          $input
     * @param string          $methodeName
     *
     * @return void
     */
    private function assertExtractedNames($expected, string $input, string $methodeName): void
    {
        $nameProcessorMock = $this->createMock(NameProcessor::class);
        $reflectionClass   = new ReflectionClass(NameProcessor::class);

        $reflectionMethod = $reflectionClass->getMethod('getDomXPathInstance');
        $reflectionMethod->setAccessible(true);

        /** @var DOMXPath $domXPath */
        $domXPath = $reflectionMethod->invoke($nameProcessorMock, $input);

        $reflectionProperty = $reflectionClass->getProperty('xPath');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($nameProcessorMock, $domXPath);

        $result = $reflectionClass->getMethod($methodeName)
            ->invoke($nameProcessorMock);

        self::assertSame($expected, $result);
    }

    /**
     * Tests extracting the plain first names of an individual.
     *
     * @test
     * @dataProvider individualNameDataProvider
     *
     * @param string $input
     * @param array  $expected
     *
     * @return void
     */
    public function getFirstNames(string $input, array $expected): void
    {
        $this->assertExtractedNames($expected[0], $input, 'getFirstNames');
    }

    /**
     * Tests extracting the plain last names of an individual.
     *
     * @test
     * @dataProvider individualNameDataProvider
     *
     * @param string $input
     * @param array  $expected
     *
     * @return void
     */
    public function getLastNames(string $input, array $expected): void
    {
        $this->assertExtractedNames($expected[1], $input, 'getLastNames');
    }

    /**
     * Tests extracting the plain first names of an individual.
     *
     * @test
     * @dataProvider individualNameDataProvider
     *
     * @param string $input
     * @param array  $expected
     *
     * @return void
     */
    public function getPreferredName(string $input, array $expected): void
    {
        // getPreferredName returns only one match, but test data is stored as array
        $this->assertExtractedNames($expected[2][0], $input, 'getPreferredName');
    }
}
