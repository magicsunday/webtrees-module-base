<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Module;

use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use JsonException;
use MagicSunday\Webtrees\ModuleBase\Module\VersionInformation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * VersionInformationTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(VersionInformation::class)]
final class VersionInformationTest extends TestCase
{
    /**
     * The bundled module version the parse falls back to.
     */
    private const string FALLBACK_VERSION = '1.0.0-fallback';

    /**
     * Invokes the private parseLatestVersion() on a VersionInformation whose
     * module reports FALLBACK_VERSION as its bundled version, so a test can
     * assert either the parsed tag or the fallback.
     *
     * @param string $body The raw HTTP response body to parse
     *
     * @return string
     *
     * @throws ReflectionException
     */
    private function invokeParse(string $body): string
    {
        $module = self::createStub(ModuleCustomInterface::class);
        $module->method('customModuleVersion')->willReturn(self::FALLBACK_VERSION);

        $sut    = new VersionInformation($module);
        $method = (new ReflectionClass(VersionInformation::class))->getMethod('parseLatestVersion');

        $result = $method->invoke($sut, $body);

        self::assertIsString($result);

        return $result;
    }

    /**
     * @return array<string, array{string, string}>
     *
     * @throws JsonException
     */
    public static function parseDataProvider(): array
    {
        // [ response body, expected version ]
        return [
            'GitHub tag_name is returned verbatim' => [
                json_encode(['tag_name' => '2.6.0'], JSON_THROW_ON_ERROR),
                '2.6.0',
            ],
            'A pre-release suffix is preserved (prefix match, not anchored)' => [
                json_encode(['tag_name' => '2.6.0-beta1'], JSON_THROW_ON_ERROR),
                '2.6.0-beta1',
            ],
            'A v-prefixed tag does not look like a version and falls back' => [
                json_encode(['tag_name' => 'v2.6.0'], JSON_THROW_ON_ERROR),
                self::FALLBACK_VERSION,
            ],
            'A missing tag_name falls back to the bundled version' => [
                json_encode(['name' => 'Release 2.6.0'], JSON_THROW_ON_ERROR),
                self::FALLBACK_VERSION,
            ],
            'A non-object JSON body falls back to the bundled version' => [
                json_encode('2.6.0', JSON_THROW_ON_ERROR),
                self::FALLBACK_VERSION,
            ],
            'A non-string tag_name falls back instead of raising a TypeError' => [
                json_encode(['tag_name' => ['2.6.0']], JSON_THROW_ON_ERROR),
                self::FALLBACK_VERSION,
            ],
        ];
    }

    /**
     * The tag_name parse is the sole functional deviation from the webtrees
     * core version check; this pins it against stubbed GitHub JSON responses.
     *
     * @param string $body
     * @param string $expected
     *
     * @throws ReflectionException
     */
    #[Test]
    #[DataProvider('parseDataProvider')]
    public function returnsParsedTagNameOrBundledVersionFallback(string $body, string $expected): void
    {
        self::assertSame($expected, $this->invokeParse($body));
    }

    /**
     * A 200 response with a malformed JSON body surfaces the JsonException
     * rather than being silently swallowed (it is not a GuzzleException).
     *
     * @throws ReflectionException
     */
    #[Test]
    public function parseLatestVersionThrowsOnMalformedJson(): void
    {
        $this->expectException(JsonException::class);

        $this->invokeParse('{ not valid json');
    }
}
