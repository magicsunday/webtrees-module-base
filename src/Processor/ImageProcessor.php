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
     * @param ModuleCustomInterface $module     The module
     * @param Individual            $individual The individual to process
     */
    public function __construct(
        /**
         * The module.
         */
        private readonly ModuleCustomInterface $module,
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

            if ($mediaFile instanceof MediaFile) {
                return $mediaFile->imageUrl($width, $height, 'contain');
            }

            if (
                $returnSilhouettes
                && ($this->individual->tree()->getPreference('USE_SILHOUETTE') !== '')
            ) {
                // assetUrl() lives on AbstractModule, not on ModuleCustomInterface.
                // Guard needed because the constructor accepts ModuleCustomInterface.
                if (method_exists($this->module, 'assetUrl') === false) {
                    return '';
                }

                $assetUrl = $this->module->assetUrl(
                    sprintf(
                        'images/silhouette-%s.svg',
                        $this->individual->sex()
                    )
                );

                return is_string($assetUrl) ? $assetUrl : '';
            }
        }

        return '';
    }
}
