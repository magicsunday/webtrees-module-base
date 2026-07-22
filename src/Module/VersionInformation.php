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
use GuzzleHttp\Exception\GuzzleException;
use JsonException;

use function is_array;
use function is_string;
use function json_decode;
use function preg_match;

use const JSON_THROW_ON_ERROR;

/**
 * Class VersionInformation.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
class VersionInformation
{
    /**
     * Constructor.
     *
     * @param ModuleCustomInterface $module The module
     */
    public function __construct(
        /**
         * The module.
         */
        private readonly ModuleCustomInterface $module,
    ) {
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
                    $client = new Client([
                        'timeout' => 3,
                    ]);

                    $response = $client->get($this->module->customModuleLatestVersionUrl());

                    if ($response->getStatusCode() === StatusCodeInterface::STATUS_OK) {
                        return $this->parseLatestVersion($response->getBody()->getContents());
                    }
                } catch (GuzzleException) {
                    // Can't connect to the server?
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
     *
     * @throws JsonException When the body is not valid JSON
     */
    private function parseLatestVersion(string $body): string
    {
        $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (is_array($json)) {
            $version = $json['tag_name'] ?? '';

            // Does the response look like a version? A non-string tag_name
            // (GitHub returns a string, but a spoofed/malformed body could carry
            // an array or object) must not reach preg_match(), which would throw
            // an uncaught TypeError instead of falling back to the bundled version.
            if (
                is_string($version)
                && (preg_match('/^\d+\.\d+\.\d+/', $version) === 1)
            ) {
                return $version;
            }
        }

        return $this->module->customModuleVersion();
    }
}
