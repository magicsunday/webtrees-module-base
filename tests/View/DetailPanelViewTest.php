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
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function explode;

/**
 * Renders the detail panel view that the three tree charts share.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversNothing]
final class DetailPanelViewTest extends TestCase
{
    /**
     * Parameters of a plain render.
     *
     * @var array<string, string>
     */
    public const array PARAMETERS = [
        'id'              => 'webtrees-fan-detail-panel',
        'moduleClass'     => 'webtrees-fan-detail-panel',
        'closeLabel'      => 'close',
        'hint'            => 'Select a person',
        'emptyText'       => 'No details',
        'individualLabel' => 'Open page',
        'centerLabel'     => 'Center chart',
    ];

    /**
     * The panel script of the chart library throws when a part is missing, so each part is there once.
     *
     * @return iterable<string, array{string}>
     */
    public static function panelParts(): iterable
    {
        foreach (['title', 'image', 'hint', 'facts', 'empty', 'actions', 'individual', 'center'] as $part) {
            yield $part => [$part];
        }
    }

    /**
     * The panel carries every part that the script looks up, once.
     */
    #[Test]
    #[DataProvider('panelParts')]
    public function carriesEveryPartOnce(string $part): void
    {
        $xpath = ViewFixture::xpathOf(ViewFixture::render('detail-panel', self::PARAMETERS));

        self::assertCount(
            1,
            ViewFixture::elements($xpath, '//aside//*[@data-panel="' . $part . '"]')
        );
    }

    /**
     * The panel is hidden until the script enables it, named by its own heading, and styled by the
     * shared class and the class of the module.
     */
    #[Test]
    public function isAHiddenOffcanvasNamedByItsTitle(): void
    {
        $xpath = ViewFixture::xpathOf(ViewFixture::render('detail-panel', self::PARAMETERS));
        $aside = ViewFixture::first($xpath, '//aside[@id="webtrees-fan-detail-panel"]');

        self::assertTrue($aside->hasAttribute('hidden'));
        self::assertSame('webtrees-fan-detail-panel-title', $aside->getAttribute('aria-labelledby'));
        self::assertSame('-1', $aside->getAttribute('tabindex'));

        $classes = explode(' ', $aside->getAttribute('class'));

        foreach (['offcanvas', 'offcanvas-end', 'webtrees-detail-panel', 'd-print-none', 'webtrees-fan-detail-panel'] as $class) {
            self::assertContains($class, $classes);
        }

        $heading = ViewFixture::first($xpath, '//*[@id="webtrees-fan-detail-panel-title"]');

        self::assertSame('title', $heading->getAttribute('data-panel'));
    }

    /**
     * The parts that the script reveals start hidden, the hint is what an empty selection shows,
     * and both actions are buttons.
     */
    #[Test]
    public function startsWithTheHintAndHiddenDetails(): void
    {
        $xpath = ViewFixture::xpathOf(ViewFixture::render('detail-panel', self::PARAMETERS));

        foreach (['image', 'empty', 'actions'] as $part) {
            self::assertTrue(ViewFixture::first($xpath, '//*[@data-panel="' . $part . '"]')->hasAttribute('hidden'));
        }

        self::assertFalse(ViewFixture::first($xpath, '//*[@data-panel="hint"]')->hasAttribute('hidden'));

        foreach (['individual', 'center'] as $action) {
            self::assertSame('button', ViewFixture::first($xpath, '//*[@data-panel="' . $action . '"]')->tagName);
        }
    }

    /**
     * The translated texts land in their places and are escaped.
     */
    #[Test]
    public function escapesTheTextsItShows(): void
    {
        $text  = 'Say "x" & <b>y</b>';
        $xpath = ViewFixture::xpathOf(ViewFixture::render('detail-panel', [
            'id'              => 'a"b',
            'moduleClass'     => 'm"c',
            'closeLabel'      => $text,
            'hint'            => $text . ' hint',
            'emptyText'       => $text . ' empty',
            'individualLabel' => $text . ' individual',
            'centerLabel'     => $text . ' center',
        ]));

        self::assertSame($text, ViewFixture::first($xpath, '//button[@data-bs-dismiss]')->getAttribute('aria-label'));
        self::assertSame($text . ' hint', ViewFixture::first($xpath, '//*[@data-panel="hint"]')->textContent);
        self::assertSame($text . ' empty', ViewFixture::first($xpath, '//*[@data-panel="empty"]')->textContent);
        self::assertSame($text . ' individual', ViewFixture::first($xpath, '//*[@data-panel="individual"]')->textContent);
        self::assertSame($text . ' center', ViewFixture::first($xpath, '//*[@data-panel="center"]')->textContent);
        self::assertSame('a"b', ViewFixture::first($xpath, '//aside')->getAttribute('id'));
        self::assertSame('a"b-title', ViewFixture::first($xpath, '//aside')->getAttribute('aria-labelledby'));
        self::assertStringContainsString('m"c', ViewFixture::first($xpath, '//aside')->getAttribute('class'));
        self::assertSame([], ViewFixture::elements($xpath, '//b'));
    }
}
