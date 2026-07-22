<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Module;

use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Registry;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use JsonException;
use Throwable;

use function is_array;
use function is_string;
use function json_decode;
use function preg_match;

use const JSON_THROW_ON_ERROR;

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
        // No update URL provided
        if ($this->module->customModuleLatestVersionUrl() === '') {
            return $this->module->customModuleVersion();
        }

        return Registry::cache()->file()->remember(
            $this->module->name() . '-latest-version',
            function (): string {
                try {
                    $response = $this->httpClient()->request(
                        'GET',
                        $this->module->customModuleLatestVersionUrl()
                    );

                    if ($response->getStatusCode() === StatusCodeInterface::STATUS_OK) {
                        return $this->parseLatestVersion($response->getBody()->getContents());
                    }
                } catch (Throwable) {
                    // An update check must never break the page it renders in. This
                    // runs inside the control panel, so anything the request or the
                    // body read throws — a transport error, a detached stream, or
                    // whatever an injected client raises — degrades to the bundled
                    // version instead of reaching the error handler.
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
