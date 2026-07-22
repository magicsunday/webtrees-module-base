<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Processor;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Tree;
use MagicSunday\Webtrees\ModuleBase\Contract\ModuleAssetUrlInterface;
use MagicSunday\Webtrees\ModuleBase\Processor\ImageProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ImageProcessorTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(ImageProcessor::class)]
final class ImageProcessorTest extends TestCase
{
    /**
     * Builds a module stub whose assetUrl() echoes the requested asset path
     * behind a fixed prefix, so a test can assert the exact silhouette path the
     * processor built from the individual's sex.
     *
     * @return ModuleCustomInterface&ModuleAssetUrlInterface
     */
    private function createModule(): ModuleCustomInterface&ModuleAssetUrlInterface
    {
        $module = self::createStubForIntersectionOfInterfaces([
            ModuleCustomInterface::class,
            ModuleAssetUrlInterface::class,
        ]);

        $module->method('assetUrl')->willReturnCallback(
            static fn (string $asset): string => '/module-asset/' . $asset
        );

        return $module;
    }

    /**
     * Builds an individual stub with the two tree preferences the processor
     * reads and the highlight media file it resolves.
     *
     * @param bool           $canShow       Whether the individual is visible
     * @param string         $showHighlight The SHOW_HIGHLIGHT_IMAGES preference value
     * @param string         $useSilhouette The USE_SILHOUETTE preference value
     * @param MediaFile|null $highlight     The resolved highlight media file (or null)
     * @param string         $sex           The individual's GEDCOM sex token
     *
     * @return Individual
     */
    private function createIndividual(
        bool $canShow,
        string $showHighlight,
        string $useSilhouette,
        ?MediaFile $highlight,
        string $sex = 'M',
    ): Individual {
        $preferences = [
            'SHOW_HIGHLIGHT_IMAGES' => $showHighlight,
            'USE_SILHOUETTE'        => $useSilhouette,
        ];

        $tree = self::createStub(Tree::class);
        $tree->method('getPreference')->willReturnCallback(
            static fn (string $name): string => $preferences[$name] ?? ''
        );

        $individual = self::createStub(Individual::class);
        $individual->method('canShow')->willReturn($canShow);
        $individual->method('tree')->willReturn($tree);
        $individual->method('findHighlightedMediaFile')->willReturn($highlight);
        $individual->method('sex')->willReturn($sex);

        return $individual;
    }

    /**
     * Builds a media file stub.
     *
     * @param bool   $isExternal Whether the media file is an external reference
     * @param bool   $fileExists Whether the referenced file exists on disk
     * @param string $url        The URL imageUrl() returns
     *
     * @return MediaFile
     */
    private function createMediaFile(bool $isExternal, bool $fileExists, string $url = '/media/photo.jpg'): MediaFile
    {
        $mediaFile = self::createStub(MediaFile::class);
        $mediaFile->method('isExternal')->willReturn($isExternal);
        $mediaFile->method('fileExists')->willReturn($fileExists);
        $mediaFile->method('imageUrl')->willReturn($url);

        return $mediaFile;
    }

    /**
     * Builds the processor under test from the given individual.
     *
     * @param Individual $individual
     *
     * @return ImageProcessor
     */
    private function createProcessor(Individual $individual): ImageProcessor
    {
        return new ImageProcessor($this->createModule(), $individual);
    }

    /**
     * A present highlight image whose file exists returns that image's URL.
     */
    #[Test]
    public function getHighlightImageUrlReturnsHighlightUrlWhenFileExists(): void
    {
        $processor = $this->createProcessor(
            $this->createIndividual(
                true,
                '1',
                '1',
                $this->createMediaFile(false, true, '/media/photo.jpg'),
            )
        );

        self::assertSame('/media/photo.jpg', $processor->getHighlightImageUrl());
    }

    /**
     * An external highlight image is returned even when its file is not present
     * on local disk (the isExternal() short-circuit).
     */
    #[Test]
    public function getHighlightImageUrlReturnsExternalHighlightUrlWhenFileMissing(): void
    {
        $processor = $this->createProcessor(
            $this->createIndividual(
                true,
                '1',
                '1',
                $this->createMediaFile(true, false, 'https://example.test/remote.jpg'),
            )
        );

        self::assertSame('https://example.test/remote.jpg', $processor->getHighlightImageUrl());
    }

    /**
     * A highlight image whose file is missing on disk falls back to the
     * sex-specific silhouette (compensating for the webtrees core 404).
     */
    #[Test]
    public function getHighlightImageUrlFallsBackToSilhouetteWhenFileMissing(): void
    {
        $processor = $this->createProcessor(
            $this->createIndividual(
                true,
                '1',
                '1',
                $this->createMediaFile(false, false),
                'F',
            )
        );

        self::assertSame('/module-asset/images/silhouette-F.svg', $processor->getHighlightImageUrl());
    }

    /**
     * With a missing file and silhouettes disabled via the call argument, the
     * processor returns an empty string rather than the silhouette.
     */
    #[Test]
    public function getHighlightImageUrlReturnsEmptyWhenFileMissingAndSilhouettesDisabledByArgument(): void
    {
        $processor = $this->createProcessor(
            $this->createIndividual(
                true,
                '1',
                '1',
                $this->createMediaFile(false, false),
            )
        );

        self::assertSame('', $processor->getHighlightImageUrl(250, 250, false));
    }

    /**
     * With no highlight media file referenced at all, the processor falls back
     * to the sex-specific silhouette.
     */
    #[Test]
    public function getHighlightImageUrlFallsBackToSilhouetteWhenNoHighlightReferenced(): void
    {
        $processor = $this->createProcessor(
            $this->createIndividual(
                true,
                '1',
                '1',
                null,
                'M',
            )
        );

        self::assertSame('/module-asset/images/silhouette-M.svg', $processor->getHighlightImageUrl());
    }

    /**
     * An individual that cannot be shown yields no image, even when a highlight
     * media file is present.
     */
    #[Test]
    public function getHighlightImageUrlReturnsEmptyWhenIndividualCannotBeShown(): void
    {
        $processor = $this->createProcessor(
            $this->createIndividual(
                false,
                '1',
                '1',
                $this->createMediaFile(false, true),
            )
        );

        self::assertSame('', $processor->getHighlightImageUrl());
    }

    /**
     * With highlight images disabled at the tree level, the processor returns
     * an empty string without consulting the media file.
     */
    #[Test]
    public function getHighlightImageUrlReturnsEmptyWhenHighlightImagesDisabled(): void
    {
        $processor = $this->createProcessor(
            $this->createIndividual(
                true,
                '',
                '1',
                $this->createMediaFile(false, true),
            )
        );

        self::assertSame('', $processor->getHighlightImageUrl());
    }

    /**
     * getSilhouetteUrl() builds the sex-specific asset URL when the individual
     * is visible and silhouettes are enabled.
     */
    #[Test]
    public function getSilhouetteUrlReturnsSexSpecificAssetUrl(): void
    {
        $processor = $this->createProcessor(
            $this->createIndividual(true, '1', '1', null, 'U')
        );

        self::assertSame('/module-asset/images/silhouette-U.svg', $processor->getSilhouetteUrl());
    }

    /**
     * getSilhouetteUrl() returns an empty string when silhouettes are disabled
     * at the tree level.
     */
    #[Test]
    public function getSilhouetteUrlReturnsEmptyWhenSilhouettesDisabled(): void
    {
        $processor = $this->createProcessor(
            $this->createIndividual(true, '1', '', null, 'F')
        );

        self::assertSame('', $processor->getSilhouetteUrl());
    }

    /**
     * getSilhouetteUrl() returns an empty string when the individual cannot be
     * shown.
     */
    #[Test]
    public function getSilhouetteUrlReturnsEmptyWhenIndividualCannotBeShown(): void
    {
        $processor = $this->createProcessor(
            $this->createIndividual(false, '1', '1', null, 'M')
        );

        self::assertSame('', $processor->getSilhouetteUrl());
    }
}
