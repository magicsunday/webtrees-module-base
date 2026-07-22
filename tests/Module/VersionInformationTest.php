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
use Fisharebest\Webtrees\Registry;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JsonException;
use MagicSunday\Webtrees\ModuleBase\Module\VersionInformation;
use MagicSunday\Webtrees\ModuleBase\Test\Double\InMemoryCacheFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

use function json_encode;
use function str_repeat;

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
     * The bundled module version the check falls back to.
     */
    private const string FALLBACK_VERSION = '1.0.0-fallback';

    /**
     * Set up.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Registry::cache(new InMemoryCacheFactory());
    }

    /**
     * Runs the real version check against a stubbed HTTP client, so the release-API
     * response can be dictated without reaching the network.
     *
     * The check memoises its result under a cache key built from the module name, so
     * the cache — not the key — is what has to be fresh. setUp() installs an in-memory
     * factory per test, which is why a stable name is safe here; with the real
     * file-backed factory the first case's result would answer every later one, and
     * outlive the process too.
     *
     * @param Response|ConnectException $response The response the client returns, or
     *                                            the transport failure it raises
     * @param StreamInterface|null      $body     Replaces the response body, for the
     *                                            case where reading it throws
     *
     * @return string
     */
    private function fetchLatestVersionFor(
        Response|ConnectException $response,
        ?StreamInterface $body = null,
    ): string {
        $module = self::createStub(ModuleCustomInterface::class);
        $module->method('customModuleVersion')->willReturn(self::FALLBACK_VERSION);
        $module->method('customModuleLatestVersionUrl')->willReturn('https://example.invalid/releases/latest');
        $module->method('name')->willReturn('version-information-test');

        $client = self::createStub(ClientInterface::class);

        if ($response instanceof ConnectException) {
            $client->method('request')->willThrowException($response);
        } else {
            $client->method('request')->willReturn(
                $body instanceof StreamInterface ? $response->withBody($body) : $response
            );
        }

        return (new VersionInformation($module, $client))->fetchLatestVersion();
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
            'Trailing text after a valid version is refused, not shown verbatim' => [
                json_encode(
                    ['tag_name' => '2.6.0 - SECURITY UPDATE, download from http://example.invalid/patch.zip'],
                    JSON_THROW_ON_ERROR
                ),
                self::FALLBACK_VERSION,
            ],
            'A trailing newline after a valid version is refused' => [
                json_encode(['tag_name' => "2.6.0\n"], JSON_THROW_ON_ERROR),
                self::FALLBACK_VERSION,
            ],
            'An absurdly long numeric component is refused' => [
                json_encode(['tag_name' => str_repeat('9', 100) . '.0.0'], JSON_THROW_ON_ERROR),
                self::FALLBACK_VERSION,
            ],
        ];
    }

    /**
     * The tag_name parse is the sole functional deviation from the webtrees core
     * version check; this pins it against stubbed GitHub JSON responses.
     *
     * @param string $body
     * @param string $expected
     *
     * @return void
     */
    #[Test]
    #[DataProvider('parseDataProvider')]
    public function returnsParsedTagNameOrBundledVersionFallback(string $body, string $expected): void
    {
        self::assertSame($expected, $this->fetchLatestVersionFor(new Response(200, [], $body)));
    }

    /**
     * A 200 response whose body is not JSON falls back to the bundled version.
     *
     * This is routine, not exotic: a captive portal, a CDN maintenance page or a
     * provider incident page all answer 200 with HTML. Letting the decode failure
     * escape would take down the control panel this check renders in — and, because
     * the exception would escape the cache callback, nothing would be memoised, so
     * every reload would retry and fail again.
     *
     * @return void
     */
    #[Test]
    public function nonJsonBodyFallsBackToTheBundledVersion(): void
    {
        self::assertSame(
            self::FALLBACK_VERSION,
            $this->fetchLatestVersionFor(new Response(200, [], '<html>503 Service Unavailable</html>'))
        );
    }

    /**
     * A failure raised while reading the response body is swallowed too. It is not a
     * GuzzleException, so a catch narrowed to that type would let it escape.
     *
     * @return void
     */
    #[Test]
    public function bodyReadFailureFallsBackToTheBundledVersion(): void
    {
        $stream = self::createStub(StreamInterface::class);
        $stream->method('getContents')->willThrowException(new RuntimeException('stream detached'));

        self::assertSame(
            self::FALLBACK_VERSION,
            $this->fetchLatestVersionFor(new Response(200, [], ''), $stream)
        );
    }

    /**
     * A non-200 response is not parsed at all — the bundled version stands.
     *
     * @return void
     *
     * @throws JsonException
     */
    #[Test]
    public function nonOkResponseFallsBackToTheBundledVersion(): void
    {
        self::assertSame(
            self::FALLBACK_VERSION,
            $this->fetchLatestVersionFor(
                new Response(404, [], json_encode(['tag_name' => '9.9.9'], JSON_THROW_ON_ERROR))
            )
        );
    }

    /**
     * A transport failure (the server is unreachable) is swallowed deliberately: an
     * update check must never break the control panel it renders in.
     *
     * @return void
     */
    #[Test]
    public function transportFailureFallsBackToTheBundledVersion(): void
    {
        self::assertSame(
            self::FALLBACK_VERSION,
            $this->fetchLatestVersionFor(
                new ConnectException('Connection refused', new Request('GET', 'https://example.invalid'))
            )
        );
    }

    /**
     * With no update URL configured the check short-circuits: no HTTP request is
     * made and the bundled version is reported.
     *
     * @return void
     */
    #[Test]
    public function emptyLatestVersionUrlSkipsTheRequest(): void
    {
        $module = self::createStub(ModuleCustomInterface::class);
        $module->method('customModuleVersion')->willReturn(self::FALLBACK_VERSION);
        $module->method('customModuleLatestVersionUrl')->willReturn('');

        $client = self::createMock(ClientInterface::class);
        $client->expects(self::never())->method('request');

        self::assertSame(self::FALLBACK_VERSION, (new VersionInformation($module, $client))->fetchLatestVersion());
    }
}
