<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Processor;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use MagicSunday\Webtrees\ModuleBase\Contract\ModuleAssetUrlInterface;

/**
 * Class ImageProcessor.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
class ImageProcessor
{
    /**
     * Constructor.
     *
     * @param ModuleCustomInterface&ModuleAssetUrlInterface $module     The module — must provide assetUrl() for silhouette URLs
     * @param Individual                                    $individual The individual to process
     */
    public function __construct(
        /**
         * The module.
         */
        private readonly ModuleCustomInterface&ModuleAssetUrlInterface $module,
        /**
         * The individual.
         */
        private readonly Individual $individual,
    ) {
    }

    /**
     * Returns the URL of a person's highlight image.
     *
     * @param int  $width             The request maximum width of the image
     * @param int  $height            The request maximum height of the image
     * @param bool $returnSilhouettes Set to TRUE to return silhouette images if this is
     *                                also enabled in the configuration
     *
     * @return string
     */
    public function getHighlightImageUrl(
        int $width = 250,
        int $height = 250,
        bool $returnSilhouettes = true,
    ): string {
        if (
            $this->individual->canShow()
            && ($this->individual->tree()->getPreference('SHOW_HIGHLIGHT_IMAGES') !== '')
        ) {
            $mediaFile = $this->individual->findHighlightedMediaFile();

            // The GEDCOM may reference a media file whose actual file is
            // missing on disk. Webtrees core's media-thumbnail endpoint 404s
            // in that case instead of substituting a silhouette itself, so
            // verify the file exists before returning its URL — otherwise
            // fall through to the silhouette branch below.
            if (
                $mediaFile instanceof MediaFile
                && ($mediaFile->isExternal() || $mediaFile->fileExists())
            ) {
                return $mediaFile->imageUrl($width, $height, 'contain');
            }

            if ($returnSilhouettes) {
                return $this->getSilhouetteUrl();
            }
        }

        return '';
    }

    /**
     * Returns the URL of the sex-specific silhouette image, intended as a
     * client-side fallback when a highlight image's media file is missing
     * (webtrees core's media-thumbnail endpoint 404s in that case instead
     * of substituting a silhouette itself).
     *
     * Returns an empty string when the individual cannot be shown or when
     * the tree has `USE_SILHOUETTE` disabled — same gating as the
     * silhouette branch in `getHighlightImageUrl()`.
     *
     * @return string
     */
    public function getSilhouetteUrl(): string
    {
        if (
            $this->individual->canShow()
            && ($this->individual->tree()->getPreference('USE_SILHOUETTE') !== '')
        ) {
            return $this->module->assetUrl(
                sprintf(
                    'images/silhouette-%s.png',
                    $this->individual->sex()
                )
            );
        }

        return '';
    }
}
