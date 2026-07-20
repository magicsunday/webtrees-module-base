<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use InvalidArgumentException;
use MagicSunday\Webtrees\ModuleBase\Model\PlaceFormatSpec;
use MagicSunday\Webtrees\ModuleBase\Model\PlaceStyle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * PlaceFormatSpecTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(PlaceFormatSpec::class)]
#[UsesClass(PlaceStyle::class)]
class PlaceFormatSpecTest extends TestCase
{
    /**
     * A negative level count would silently invert Place::firstParts(), which
     * drops the LAST segment instead of keeping the first N. The tree preference
     * feeding this value is an unvalidated database string, and "-1" is the old
     * automatic sentinel that really does sit in existing installations.
     *
     * @return void
     */
    #[Test]
    public function aNegativeLevelCountIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('must not be negative');

        new PlaceFormatSpec(PlaceStyle::Levels, -1);
    }
}
