<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Traits;

use Fisharebest\Localization\Translation;
use MagicSunday\Webtrees\ModuleBase\Module\VersionInformation;

/**
 * Shared ModuleCustomInterface helpers used by the chart modules.
 *
 * Consuming classes must define `CUSTOM_*` constants and `resourcesFolder()`.
 */
trait ModuleCustomTrait
{
    use \Fisharebest\Webtrees\Module\ModuleCustomTrait;

    /**
     * Returns the module author's name.
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * Returns the currently installed module version string.
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * Returns the GitHub API URL used to check for newer releases.
     */
    public function customModuleLatestVersionUrl(): string
    {
        return self::CUSTOM_LATEST_VERSION;
    }

    /**
     * Fetches the latest published release version from GitHub, with a 24-hour file cache.
     * Falls back to the installed version when the API is unreachable.
     */
    public function customModuleLatestVersion(): string
    {
        return (new VersionInformation($this))->fetchLatestVersion();
    }

    /**
     * Returns the URL of the module's GitHub issue tracker.
     */
    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_SUPPORT_URL;
    }

    /**
     * Loads translations from a compiled MO file for the requested language.
     * Returns an empty array when no translation file exists for that language.
     *
     * @return array<string, string>
     */
    public function customTranslations(string $language): array
    {
        $languageFile = $this->resourcesFolder() . 'lang/' . $language . '/messages.mo';
        $translations = file_exists($languageFile) ? (new Translation($languageFile))->asArray() : [];

        /** @var array<string, string> $translations */
        return $translations;
    }
}
