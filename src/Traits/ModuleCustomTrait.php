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

    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    public function customModuleLatestVersionUrl(): string
    {
        return self::CUSTOM_LATEST_VERSION;
    }

    public function customModuleLatestVersion(): string
    {
        return (new VersionInformation($this))->fetchLatestVersion();
    }

    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_SUPPORT_URL;
    }

    /**
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
