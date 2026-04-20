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
use Fisharebest\Webtrees\Individual;
use MagicSunday\Webtrees\ModuleBase\Processor\NameProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

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
    public static function convertToHtmlEntitiesDataProvider(): array
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
     * @param string $input
     * @param string $expected
     *
     * @return void
     *
     * @throws ReflectionException
     */
    #[Test]
    #[DataProvider('convertToHtmlEntitiesDataProvider')]
    public function convertToHtmlEntities(string $input, string $expected): void
    {
        // Create mock
        $nameProcessorMock = self::createStub(NameProcessor::class);

        $reflectionClass  = new ReflectionClass(NameProcessor::class);
        $reflectionMethod = $reflectionClass->getMethod('convertToHtmlEntities');

        $result = $reflectionMethod->invokeArgs($nameProcessorMock, [$input]);

        self::assertSame($expected, $result);
    }

    /**
     * @return array<int, array{string, array{list<string>, list<string>, list<string>}}>
     */
    public static function individualNameDataProvider(): array
    {
        // [ input, expected => [ First names, Last names, Preferred first name, Nick names ] ]
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
                '<span class="NAME" dir="auto" translate="no">Max <span class="starredname">Peter</span> <q class="wt-nickname">Mäxchen</q> <span class="SURN">Mustermann</span></span>',
                [
                    [
                        'Max',
                        'Peter',
                    ],
                    [
                        'Mustermann',
                    ],
                    [
                        'Peter',
                    ],
                ],
            ],

            [
                '<span class="NAME" dir="auto" translate="no">Max <q class="wt-nickname">Mäxchen</q> <span class="starredname">Peter</span> <span class="SURN">Mustermann</span></span>',
                [
                    [
                        'Max',
                        'Peter',
                    ],
                    [
                        'Mustermann',
                    ],
                    [
                        'Peter',
                    ],
                ],
            ],

            [
                '<span class="NAME" dir="auto" translate="no">Max <q class="wt-nickname">Mäxchen</q> Hermann <span class="SURN">Mustermann</span></span>',
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

            [
                '<span class="NAME" dir="auto" translate="no">José <span class="starredname">Antonio</span> Carlo <span class="SURN">Gómez</span> <span class="SURN">Iglesias</span></span>',
                [
                    [
                        'José',
                        'Antonio',
                        'Carlo',
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
     *
     * @throws ReflectionException
     */
    private function assertExtractedNames($expected, string $input, string $methodeName): void
    {
        $reflectionClass  = new ReflectionClass(NameProcessor::class);
        $reflectionMethod = $reflectionClass->getMethod('getDomXPathInstance');

        // Create mock
        $nameProcessorMock = self::createStub(NameProcessor::class);

        /** @var DOMXPath $domXPath */
        $domXPath = $reflectionMethod->invoke($nameProcessorMock, $input);

        $reflectionProperty = $reflectionClass->getProperty('xPath');
        $reflectionProperty->setValue($nameProcessorMock, $domXPath);

        $result = $reflectionClass
            ->getMethod($methodeName)
            ->invoke($nameProcessorMock);

        self::assertSame($expected, $result);
    }

    /**
     * Tests extracting the plain first names of an individual.
     *
     * @param string               $input
     * @param array<int, string[]> $expected
     *
     * @return void
     *
     * @throws ReflectionException
     */
    #[Test]
    #[DataProvider('individualNameDataProvider')]
    public function getFirstNames(string $input, array $expected): void
    {
        $this->assertExtractedNames($expected[0], $input, 'getFirstNames');
    }

    /**
     * Tests extracting the plain last names of an individual.
     *
     * @param string               $input
     * @param array<int, string[]> $expected
     *
     * @return void
     *
     * @throws ReflectionException
     */
    #[Test]
    #[DataProvider('individualNameDataProvider')]
    public function getLastNames(string $input, array $expected): void
    {
        $this->assertExtractedNames($expected[1], $input, 'getLastNames');
    }

    /**
     * Tests extracting the plain first names of an individual.
     *
     * @param string               $input
     * @param array<int, string[]> $expected
     *
     * @return void
     *
     * @throws ReflectionException
     */
    #[Test]
    #[DataProvider('individualNameDataProvider')]
    public function getPreferredName(string $input, array $expected): void
    {
        // getPreferredName returns only one match, but test data is stored as an array
        $this->assertExtractedNames($expected[2][0], $input, 'getPreferredName');
    }

    /**
     * Invokes the real getMarriedSurnames() on a stubbed NameProcessor whose
     * $individual property is set via reflection. Required because
     * self::createStub() overrides public methods with no-op stubs — the real
     * method body is reachable only via Reflection::invoke().
     *
     * @param array<int, array<string, string>> $individualNames
     * @param Individual|null                   $spouse
     *
     * @return string[]
     *
     * @throws ReflectionException
     */
    private function invokeGetMarriedSurnames(array $individualNames, ?Individual $spouse = null): array
    {
        $individualStub = self::createStub(Individual::class);
        $individualStub->method('getAllNames')->willReturn($individualNames);

        $processorStub = self::createStub(NameProcessor::class);

        $reflectionClass = new ReflectionClass(NameProcessor::class);
        $reflectionClass->getProperty('individual')->setValue($processorStub, $individualStub);

        $result = $reflectionClass->getMethod('getMarriedSurnames')->invoke($processorStub, $spouse);

        self::assertIsArray($result);

        return array_values(array_filter($result, is_string(...)));
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function getMarriedSurnamesReturnsEmptyWhenNoMarnmRecord(): void
    {
        self::assertSame([], $this->invokeGetMarriedSurnames([
            ['type' => 'NAME', 'surn' => 'Schmidt'],
        ]));
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function getMarriedSurnamesReturnsMarnmSurnameWhenNoSpouseGiven(): void
    {
        self::assertSame(['Müller'], $this->invokeGetMarriedSurnames([
            ['type' => 'NAME', 'surn' => 'Schmidt'],
            ['type' => '_MARNM', 'surn' => 'Müller'],
        ]));
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function getMarriedSurnamesSplitsMultipleSurnameParts(): void
    {
        self::assertSame(['Müller', 'Meier'], $this->invokeGetMarriedSurnames([
            ['type' => '_MARNM', 'surn' => 'Müller Meier'],
        ]));
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function getMarriedSurnamesMatchesSpouseSurname(): void
    {
        $spouseStub = self::createStub(Individual::class);
        $spouseStub->method('getAllNames')->willReturn([
            ['type' => 'NAME', 'surn' => 'Müller'],
        ]);

        // The first _MARNM ("Andere") doesn't match the spouse's surname,
        // so it must be skipped; the second _MARNM ("Müller") matches.
        self::assertSame(['Müller'], $this->invokeGetMarriedSurnames(
            [
                ['type' => 'NAME', 'surn' => 'Schmidt'],
                ['type' => '_MARNM', 'surn' => 'Andere'],
                ['type' => '_MARNM', 'surn' => 'Müller'],
            ],
            $spouseStub
        ));
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function getMarriedSurnamesReturnsEmptyWhenSpouseSurnameDoesNotMatchAnyMarnm(): void
    {
        $spouseStub = self::createStub(Individual::class);
        $spouseStub->method('getAllNames')->willReturn([
            ['type' => 'NAME', 'surn' => 'Schmidt'],
        ]);

        self::assertSame([], $this->invokeGetMarriedSurnames(
            [
                ['type' => '_MARNM', 'surn' => 'Müller'],
            ],
            $spouseStub
        ));
    }
}
