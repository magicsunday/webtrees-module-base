<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Module;

use Exception;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Registry;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use JsonException;
use Psr\Http\Message\StreamInterface;

use function is_array;
use function is_string;
use function json_decode;
use function parse_url;
use function preg_match;
use function strlen;
use function strtolower;

use const JSON_THROW_ON_ERROR;
use const PHP_URL_SCHEME;

/**
 * Overrides the webtrees core module version check so an update notice can be
 * derived from a GitHub "latest release" API response. The sole functional
 * deviation from the core check is the response format: the core reads the
 * response body directly as a plain version string, whereas GitHub returns a
 * JSON object whose `tag_name` carries the version (see parseLatestVersion()).
 *
 * No chart module references this class directly: it is instantiated by this
 * library's own `Traits\ModuleCustomTrait::customModuleLatestVersion()`, which
 * overrides the core method named above and is invoked by the webtrees control
 * panel. It is therefore not dead code despite the absence of a direct consumer
 * reference.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class VersionInformation
{
    /**
     * The nesting depth json_decode() is allowed. A release payload is a flat
     * object; anything deeper is not something this parser needs to walk.
     */
    private const int MAX_JSON_DEPTH = 32;

    /**
     * A release tag that is a whole semantic version, optionally with a
     * pre-release/build suffix. Anchored end to end with \A/\z (not ^/$, which
     * would also accept a trailing newline) so the value rendered in the control
     * panel is exactly what was validated.
     */
    private const string VERSION_TAG_PATTERN = '/\A\d{1,5}\.\d{1,5}\.\d{1,5}(?:[-+][0-9A-Za-z.\-+]{1,32})?\z/';

    /**
     * The inclusive byte ceiling for the release response body. A release
     * payload is a small JSON object, so a body larger than this is refused
     * unparsed rather than buffered whole — a hostile endpoint cannot exhaust
     * the control panel's memory by streaming an oversized response.
     */
    private const int MAX_BODY_BYTES = 262144;

    /**
     * The connection-establishment timeout, in seconds. Bounds how long each
     * redirect hop may spend opening its connection; combined with the capped
     * redirect count it keeps the connection phase from running away. The total
     * transfer time is governed separately by the client's own timeout.
     */
    private const int CONNECT_TIMEOUT_SECONDS = 3;

    /**
     * The maximum number of redirects the release request follows.
     */
    private const int MAX_REDIRECTS = 3;

    /**
     * The request timeout, in seconds. It complements connect_timeout — which
     * bounds only opening the connection — with a bound on the request itself,
     * so a stalled or slow endpoint cannot hold the control-panel check open.
     * The exact enforcement (a whole-transfer deadline vs a per-read one)
     * depends on the Guzzle handler the runtime selects for a streamed request.
     */
    private const int REQUEST_TIMEOUT_SECONDS = 10;

    /**
     * Constructor.
     *
     * @param ModuleCustomInterface $module     The module
     * @param ClientInterface|null  $httpClient The HTTP client used to query the
     *                                          release API. Optional: consumers pass
     *                                          nothing and get the default client,
     *                                          while a test supplies one instead of
     *                                          reaching the network.
     */
    public function __construct(
        private ModuleCustomInterface $module,
        private ?ClientInterface $httpClient = null,
    ) {
    }

    /**
     * Returns the HTTP client to query the release API with.
     *
     * @return ClientInterface
     */
    private function httpClient(): ClientInterface
    {
        return $this->httpClient ?? new Client([
            'timeout' => 3,
        ]);
    }

    /**
     * Returns whether the URL uses an http(s) scheme. Any other scheme
     * (file://, php://, data://, …) is refused before the URL reaches the HTTP
     * client, so a misconfigured or hostile update URL cannot be used to read a
     * local resource.
     *
     * @param string $url The update URL to check
     *
     * @return bool
     */
    private function isHttpUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (!is_string($scheme)) {
            return false;
        }

        $scheme = strtolower($scheme);

        return ($scheme === 'http') || ($scheme === 'https');
    }

    /**
     * Reads the response body into a string capped at {@see self::MAX_BODY_BYTES},
     * or null when there is no usable body — the stream is empty or it exceeds
     * the cap. A single read() may return fewer bytes than requested even when
     * more follow (chunked or network-backed streams), so the read accumulates
     * until EOF rather than treating one short read as the whole body. It reads
     * one byte past the cap to tell "at cap" from "over cap", refusing an
     * oversized body rather than parsing a truncated one.
     *
     * @param StreamInterface $stream The response body to read
     *
     * @return string|null The body, or null when it is empty or exceeds the cap
     */
    private function readCappedBody(StreamInterface $stream): ?string
    {
        $body = '';

        while (!$stream->eof()) {
            $chunk = $stream->read(self::MAX_BODY_BYTES + 1 - strlen($body));

            if ($chunk === '') {
                break;
            }

            $body .= $chunk;

            if (strlen($body) > self::MAX_BODY_BYTES) {
                return null;
            }
        }

        return $body === '' ? null : $body;
    }

    /**
     * This method an extended version of
     * ModuleCustomTrait::customModuleLatestVersion, allowing to automatically
     * use the latest GitHub release version.
     *
     * @return string The latest version number
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomTrait::customModuleLatestVersion
     */
    public function fetchLatestVersion(): string
    {
        $url = $this->module->customModuleLatestVersionUrl();

        // No update URL, or one whose scheme is not http(s): report the bundled
        // version without a request. The scheme guard keeps a misconfigured or
        // hostile URL (file://, php://, …) from being handed to the HTTP client.
        if (($url === '') || !$this->isHttpUrl($url)) {
            return $this->module->customModuleVersion();
        }

        return Registry::cache()->file()->remember(
            $this->module->name() . '-latest-version',
            function () use ($url): string {
                try {
                    $response = $this->httpClient()->request('GET', $url, [
                        'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
                        // A request timeout on top of connect_timeout, so a
                        // stalled or slow endpoint cannot hold the control panel open.
                        'timeout' => self::REQUEST_TIMEOUT_SECONDS,
                        // Stream the body instead of buffering it whole, so
                        // readCappedBody() can refuse an oversized response
                        // before it is held in memory.
                        'stream' => true,
                        // Bound and pin redirects: at most a few hops, only over
                        // http(s), so the scheme guard above cannot be sidestepped
                        // by a redirect to another scheme or an unbounded chain.
                        'allow_redirects' => [
                            'max'       => self::MAX_REDIRECTS,
                            'protocols' => ['http', 'https'],
                            'strict'    => true,
                        ],
                    ]);

                    if ($response->getStatusCode() === StatusCodeInterface::STATUS_OK) {
                        $body = $this->readCappedBody($response->getBody());

                        if ($body !== null) {
                            return $this->parseLatestVersion($body);
                        }
                    }
                } catch (Exception) {
                    // An update check must not break the control panel over an
                    // operational failure. This runs inside the admin page, so a
                    // transport error, a detached stream, a non-JSON body, or whatever
                    // the request or an injected client raises degrades to the bundled
                    // version instead of reaching the error handler. A genuine
                    // programming Error is deliberately not caught here: it should
                    // surface loudly rather than be masked and cached as the fallback
                    // for a day.
                }

                return $this->module->customModuleVersion();
            },
            86400
        );
    }

    /**
     * Parses the latest version from a GitHub "latest release" API response body.
     *
     * This is the one functional deviation from the webtrees core version check:
     * the core reads the response body directly as a plain version string, while
     * GitHub's API returns a JSON object whose `tag_name` carries the version.
     * Falls back to the module's own bundled version when the body carries no
     * usable version tag.
     *
     * @param string $body The raw HTTP response body
     *
     * @return string The parsed version number, or the bundled module version as a fallback
     */
    private function parseLatestVersion(string $body): string
    {
        try {
            $json = json_decode($body, true, self::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            // A 200 response carrying something other than JSON is routine — a
            // captive portal, a CDN maintenance page, a provider incident page.
            // None of them justify breaking the control panel.
            return $this->module->customModuleVersion();
        }

        if (is_array($json)) {
            $version = $json['tag_name'] ?? '';

            // Validate the WHOLE tag, not a prefix of it. A non-string tag_name
            // (GitHub returns a string, but a spoofed/malformed body could carry
            // an array or object) must not reach preg_match(), which would throw
            // an uncaught TypeError. And the value is rendered into the control
            // panel's upgrade notice, so a tag that merely STARTS like a version
            // ("2.6.0 — download the patch from …") must not pass and be shown
            // there verbatim for the next 24 hours.
            if (
                is_string($version)
                && (preg_match(self::VERSION_TAG_PATTERN, $version) === 1)
            ) {
                return $version;
            }
        }

        return $this->module->customModuleVersion();
    }
}
