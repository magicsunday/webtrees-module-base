<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Testing;

use Fisharebest\Webtrees\Site;
use MagicSunday\Webtrees\ModuleBase\Testing\MediaDirectory;
use MagicSunday\Webtrees\ModuleBase\Testing\RealTreeFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function basename;
use function file_get_contents;
use function is_dir;
use function is_file;
use function restore_error_handler;
use function set_error_handler;
use function str_ends_with;
use function str_starts_with;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Tests that the media directory of a test is created with the media folder that the site reads, that
 * it holds the files that are written into it, and that it is gone after the removal.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(MediaDirectory::class)]
#[UsesClass(RealTreeFixture::class)]
final class MediaDirectoryTest extends TestCase
{
    /**
     * Boots webtrees on a real schema, because the site preferences live in its database.
     */
    protected function setUp(): void
    {
        parent::setUp();

        RealTreeFixture::boot();
    }

    /**
     * Restores the minimal database of the other tests.
     */
    protected function tearDown(): void
    {
        RealTreeFixture::restoreMinimalDatabase();

        parent::tearDown();
    }

    /**
     * The site reads its media from the folder that the helper creates, so the index directory of the
     * site is the new directory below the temporary directory and holds an empty media folder.
     */
    #[Test]
    public function theSiteReadsItsMediaFromTheNewDirectory(): void
    {
        $directory = new MediaDirectory();
        $directory->create('module-base-media-directory-test-');

        $indexDirectory = Site::getPreference('INDEX_DIRECTORY');

        self::assertTrue(str_starts_with($indexDirectory, sys_get_temp_dir() . '/module-base-media-directory-test-'));
        self::assertTrue(str_ends_with($indexDirectory, '/'));
        self::assertTrue(is_dir($indexDirectory . 'media'));

        $directory->remove();
    }

    /**
     * A file that is written is in the media folder with a content, so that the image of a person is
     * referenced.
     */
    #[Test]
    public function aWrittenFileIsInTheMediaFolder(): void
    {
        $directory = new MediaDirectory();
        $directory->create('module-base-media-directory-test-');
        $directory->writeFile('photo.jpg');

        $path = Site::getPreference('INDEX_DIRECTORY') . 'media/photo.jpg';

        self::assertSame('image', file_get_contents($path));

        $directory->remove();
    }

    /**
     * A file that cannot be written is no silent gap, because the test would then check an image that
     * can never be shown.
     */
    #[Test]
    public function aFileThatCannotBeWrittenFails(): void
    {
        $directory = new MediaDirectory();
        $directory->create('module-base-media-directory-test-');

        set_error_handler(static fn (): bool => true);

        try {
            $this->expectException(RuntimeException::class);

            $directory->writeFile('missing-folder/photo.jpg');
        } finally {
            restore_error_handler();
            $directory->remove();
        }
    }

    /**
     * A directory that cannot be created fails with an exception and leaves nothing to remove, so the
     * tear down of the test that follows raises no warning.
     */
    #[Test]
    public function aDirectoryThatCannotBeCreatedFailsAndLeavesNothingToRemove(): void
    {
        $blocker = tempnam(sys_get_temp_dir(), 'module-base-blocker-');

        self::assertIsString($blocker);

        $directory = new MediaDirectory();
        $warnings  = 0;

        set_error_handler(static function () use (&$warnings): bool {
            ++$warnings;

            return true;
        });

        try {
            $directory->create(basename($blocker) . '/sub-');

            self::fail('The creation below a file has to fail.');
        } catch (RuntimeException) {
            $warningsOfTheCreation = $warnings;

            $directory->remove();
        } finally {
            restore_error_handler();
            unlink($blocker);
        }

        self::assertSame($warningsOfTheCreation, $warnings);
    }

    /**
     * The removal takes the files and both folders away, and a second removal changes nothing.
     */
    #[Test]
    public function theRemovalTakesTheFilesAndTheFoldersAway(): void
    {
        $directory = new MediaDirectory();
        $directory->create('module-base-media-directory-test-');
        $directory->writeFile('one.jpg');
        $directory->writeFile('two.jpg');

        $indexDirectory = Site::getPreference('INDEX_DIRECTORY');

        $directory->remove();

        self::assertFalse(is_file($indexDirectory . 'media/one.jpg'));
        self::assertFalse(is_file($indexDirectory . 'media/two.jpg'));
        self::assertFalse(is_dir($indexDirectory . 'media'));
        self::assertFalse(is_dir($indexDirectory));

        $directory->remove();

        self::assertFalse(is_dir($indexDirectory));
    }

    /**
     * A helper that never created a directory removes nothing and does not fail, as in the tear down
     * of a test whose set up stopped early.
     */
    #[Test]
    public function aRemovalWithoutAnEarlierCreationChangesNothing(): void
    {
        $this->expectNotToPerformAssertions();

        (new MediaDirectory())->remove();
    }
}
