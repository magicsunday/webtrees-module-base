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
use MagicSunday\Webtrees\ModuleBase\Testing\MarkupFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function trim;

/**
 * Renders the buttons of the chart toolbar that the chart modules share, whose titles are translated
 * texts that the module passes in.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversNothing]
final class ToolbarButtonViewsTest extends TestCase
{
    /**
     * Returns the shared button views with the id of the button that the page script looks up.
     *
     * @return array<string, array{string, string}>
     */
    public static function buttons(): array
    {
        return [
            'center'     => ['center-button', 'centerButton'],
            'png export' => ['png-export-button', 'exportPNG'],
            'svg export' => ['svg-export-button', 'exportSVG'],
        ];
    }

    /**
     * Returns the names of the shared button views.
     *
     * @return array<string, array{string}>
     */
    public static function buttonViews(): array
    {
        return array_map(
            static fn (array $button): array => [$button[0]],
            self::buttons()
        );
    }

    /**
     * A translation can contain a quote, an ampersand or a tag. The title is an attribute value, so
     * it is escaped and keeps the translated text as one value.
     */
    #[Test]
    #[DataProvider('buttons')]
    public function keepsATranslatedTitleInsideItsAttribute(string $view, string $id): void
    {
        $title = 'Say "more" &amp; <b>less</b>';
        $xpath = MarkupFixture::xpathOf(ViewFixture::render($view, ['title' => $title]));

        $button = MarkupFixture::first($xpath, '//button');

        self::assertSame($title, $button->getAttribute('title'));
        self::assertSame($id, $button->getAttribute('id'));
    }

    /**
     * Each button carries one icon and no other text, so the title is the only name of the button.
     */
    #[Test]
    #[DataProvider('buttonViews')]
    public function carriesExactlyOneIcon(string $view): void
    {
        $xpath = MarkupFixture::xpathOf(ViewFixture::render($view, ['title' => 'Title']));

        self::assertCount(1, MarkupFixture::elements($xpath, '//button//svg'));
        self::assertSame('', trim(MarkupFixture::first($xpath, '//button')->textContent));
    }
}
