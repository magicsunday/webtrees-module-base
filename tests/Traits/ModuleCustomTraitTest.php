<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Traits;

use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use MagicSunday\Webtrees\ModuleBase\Traits\ModuleCustomTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

use function array_diff;
use function copy;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Pins what the shared custom-module trait does for a consuming chart module:
 * the accessors surface that module's own CUSTOM_* constants, and
 * customTranslations() reads `{resourcesFolder}lang/{language}/messages.mo`,
 * returning an empty catalogue when the language has none.
 *
 * customModuleLatestVersion() is deliberately absent: it performs network I/O
 * through VersionInformation and is covered by that class's own test.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversTrait(ModuleCustomTrait::class)]
final class ModuleCustomTraitTest extends TestCase
{
    /**
     * Temporary resource folders created by a test, removed again in tearDown()
     * so a failing assertion cannot leak them into the system temp directory.
     *
     * @var list<string>
     */
    private array $temporaryFolders = [];

    /**
     * Removes every folder a test registered, so a failing assertion cannot
     * leak a directory tree into the system temp directory.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->temporaryFolders as $folder) {
            $this->removeRecursively($folder);
        }

        $this->temporaryFolders = [];

        parent::tearDown();
    }

    /**
     * Removes a directory tree created by a test.
     *
     * @param string $path
     *
     * @return void
     */
    private function removeRecursively(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);

        if ($entries === false) {
            return;
        }

        foreach (array_diff($entries, ['.', '..']) as $entry) {
            $child = $path . '/' . $entry;

            if (is_dir($child)) {
                $this->removeRecursively($child);
            } else {
                unlink($child);
            }
        }

        rmdir($path);
    }

    /**
     * Returns a unique, tracked temporary resources folder.
     *
     * @return string
     */
    private function createResourcesFolder(): string
    {
        $folder = sys_get_temp_dir() . '/module-base-test-' . uniqid('', true);

        $this->temporaryFolders[] = $folder;

        return $folder . '/';
    }

    /**
     * The composition itself is the contract: webtrees' own trait contributes
     * assetUrl() and getAssetAction(), which the chart modules rely on and no
     * behavioural test here reaches, because this library's trait overrides
     * every other method of the interface.
     */
    #[Test]
    public function composesWebtreesOwnCustomModuleTraitForAssetHandling(): void
    {
        self::assertContains(
            \Fisharebest\Webtrees\Module\ModuleCustomTrait::class,
            (new ReflectionClass(ModuleCustomTrait::class))->getTraitNames(),
        );
    }

    /**
     * Builds a class that composes the trait the way a chart module does: the
     * CUSTOM_* constants and resourcesFolder() are the two things a consumer
     * must supply.
     *
     * @param string $resourcesFolder Absolute path used as the module's resources folder
     *
     * @return ModuleCustomInterface
     */
    private function createModule(string $resourcesFolder): ModuleCustomInterface
    {
        return new class($resourcesFolder) extends AbstractModule implements ModuleCustomInterface {
            use ModuleCustomTrait;

            /**
             * The value customModuleAuthorName() must hand back unchanged.
             */
            public const string CUSTOM_AUTHOR = 'Rico Sonntag';

            /**
             * The installed version customModuleVersion() reports.
             */
            public const string CUSTOM_VERSION = '9.8.7';

            /**
             * Despite its name this holds a URL, not a version: the trait maps
             * it to customModuleLatestVersionUrl().
             */
            public const string CUSTOM_LATEST_VERSION = 'https://example.test/releases/latest';

            /**
             * The URL customModuleSupportUrl() reports to the control panel.
             */
            public const string CUSTOM_SUPPORT_URL = 'https://example.test/support';

            /**
             * @param string $resourcesFolder Stands in for the module's shipped resources directory
             */
            public function __construct(
                private readonly string $resourcesFolder,
            ) {
            }

            /**
             * Replaces AbstractModule's real resources path with the temporary
             * folder the test wrote its catalogue into.
             *
             * @return string
             */
            public function resourcesFolder(): string
            {
                return $this->resourcesFolder;
            }
        };
    }

    /**
     * Copies a compiled catalogue of the fixtures into the folder where the trait looks for the language.
     *
     * @param string $resources The resources folder of the module, with a trailing slash
     * @param string $language  The language the catalogue is installed for
     * @param string $fixture   The path of the fixture catalogue below the fixture catalogues
     *
     * @throws RuntimeException When the directory cannot be created or the file cannot be copied
     */
    private function installCatalogue(string $resources, string $language, string $fixture): void
    {
        $directory = $resources . 'lang/' . $language;

        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }

        if (!copy(__DIR__ . '/../fixtures/catalogues/' . $fixture, $directory . '/messages.mo')) {
            throw new RuntimeException(sprintf('Catalogue "%s" was not copied', $fixture));
        }
    }

    /**
     * The accessors exist to hand the consuming module's own constants to
     * webtrees, so the value must arrive unchanged.
     */
    #[Test]
    public function accessorsReturnTheConsumingModulesConstants(): void
    {
        $module = $this->createModule($this->createResourcesFolder());

        self::assertSame('Rico Sonntag', $module->customModuleAuthorName());
        self::assertSame('9.8.7', $module->customModuleVersion());
        self::assertSame('https://example.test/releases/latest', $module->customModuleLatestVersionUrl());
        self::assertSame('https://example.test/support', $module->customModuleSupportUrl());
    }

    /**
     * A language without a compiled catalogue must yield an empty array rather
     * than an error — webtrees asks every module for every active language.
     */
    #[Test]
    public function customTranslationsReturnsEmptyArrayWhenNoCatalogueExists(): void
    {
        $module = $this->createModule($this->createResourcesFolder());

        self::assertSame([], $module->customTranslations('de'));
    }

    /**
     * Pins the lookup path and the parse: the catalogue is read from
     * `{resourcesFolder}lang/{language}/messages.mo`, each language gets its own, and a language
     * without one gets nothing. The first fixture holds a single entry, which is its first one, and the
     * second holds the entry `404` besides.
     */
    #[Test]
    public function customTranslationsReadsTheCompiledCatalogueOfTheRequestedLanguage(): void
    {
        $resources = $this->createResourcesFolder();

        $this->installCatalogue($resources, 'de', 'no-plural-entry/cs/messages.mo');
        $this->installCatalogue($resources, 'nl', 'numeric-msgid/cs/messages.mo');

        $module = $this->createModule($resources);

        $german = $module->customTranslations('de');
        $dutch  = $module->customTranslations('nl');

        self::assertSame(['Hello' => 'Ahoj'], $german);
        self::assertSame('Ahoj', $dutch['Hello'] ?? null);
        self::assertSame('Nenalezeno', $dutch['404'] ?? null);
        self::assertSame([], $module->customTranslations('fr'));
    }
}
