<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Testing;

use Fisharebest\Webtrees\Site;
use RuntimeException;

use function file_put_contents;
use function glob;
use function mkdir;
use function rmdir;
use function strlen;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Owns the temporary index directory whose media folder holds the image files of a test, and points
 * the site at it, so a person shows an image only when its file is there.
 *
 * A test creates the directory in its set up, writes the files of the people that show an image and
 * removes the directory in its tear down. The helper changes a preference of the site, so a test that
 * uses it boots the real schema first.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class MediaDirectory
{
    /**
     * The index directory with a trailing slash, or an empty string while there is none.
     */
    private string $indexDirectory = '';

    /**
     * Creates an empty media folder in a new temporary index directory and makes it the index directory
     * of the site.
     *
     * @param string $prefix The start of the name of the temporary directory
     */
    public function create(string $prefix): void
    {
        $this->indexDirectory = sys_get_temp_dir() . '/' . $prefix . uniqid('', true) . '/';

        if (!mkdir($this->indexDirectory . 'media', 0o777, true)) {
            throw new RuntimeException('The media directory could not be created.');
        }

        Site::setPreference('INDEX_DIRECTORY', $this->indexDirectory);
    }

    /**
     * Writes an image file into the media folder.
     *
     * @param string $name The file name of the image
     */
    public function writeFile(string $name): void
    {
        if (file_put_contents($this->indexDirectory . 'media/' . $name, 'image') !== strlen('image')) {
            throw new RuntimeException('The media file could not be written.');
        }
    }

    /**
     * Removes the media files and the directories, when there are any.
     */
    public function remove(): void
    {
        if ($this->indexDirectory === '') {
            return;
        }

        $files = glob($this->indexDirectory . 'media/*');

        foreach (($files === false) ? [] : $files as $file) {
            unlink($file);
        }

        rmdir($this->indexDirectory . 'media');
        rmdir($this->indexDirectory);

        $this->indexDirectory = '';
    }
}
