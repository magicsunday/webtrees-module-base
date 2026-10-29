<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\View;

use MagicSunday\Webtrees\ModuleBase\Test\Double\ViewFixture;
use MagicSunday\Webtrees\ModuleBase\View\SharedViews;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Registers the views of the package and renders the detail panel through them.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(SharedViews::class)]
final class SharedViewsTest extends TestCase
{
    /**
     * Webtrees resolves a view of the package under its namespace once the module registered it.
     */
    #[Test]
    public function rendersAViewUnderTheRegisteredNamespace(): void
    {
        SharedViews::register();
        SharedViews::register();

        $xpath = ViewFixture::xpathOf(
            ViewFixture::render('detail-panel', DetailPanelViewTest::PARAMETERS)
        );

        self::assertCount(1, ViewFixture::elements($xpath, '//aside'));
    }
}
