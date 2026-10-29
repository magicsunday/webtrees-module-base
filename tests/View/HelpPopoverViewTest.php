<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\View;

use DOMElement;
use MagicSunday\Webtrees\ModuleBase\Test\Double\ViewFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function explode;
use function trim;

/**
 * Renders the help popover view that the chart modules share.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversNothing]
final class HelpPopoverViewTest extends TestCase
{
    /**
     * Rows of a chart with two modes.
     *
     * @var list<array{key: string, text: string, mode?: string}>
     */
    public const array ROWS = [
        ['key' => 'Right-click', 'text' => 'Show details', 'mode' => 'tooltip'],
        ['key' => 'Tab key', 'text' => 'Move between the persons'],
        ['key' => 'Click', 'text' => 'Show in the panel', 'mode' => 'panel'],
        ['key' => 'Ctrl + scroll', 'text' => 'Zoom'],
    ];

    /**
     * The button tells assistive technology which popover it controls and that it starts closed.
     * The popover is a named region with the id the button points to.
     */
    #[Test]
    public function exposesTheStateOfThePopoverOnTheButton(): void
    {
        $xpath = ViewFixture::xpathOf($this->render());

        $button  = ViewFixture::first($xpath, '//button[contains(concat(" ", @class, " "), " btn-help ")]');
        $popover = ViewFixture::first($xpath, '//div[contains(concat(" ", @class, " "), " help-popover ")]');

        self::assertSame('false', $button->getAttribute('aria-expanded'));
        self::assertSame('my-help', $popover->getAttribute('id'));
        self::assertSame('my-help', $button->getAttribute('aria-controls'));
        self::assertSame('region', $popover->getAttribute('role'));
        self::assertSame('Show help', $popover->getAttribute('aria-label'));
        self::assertSame('Show help', $button->getAttribute('title'));
        self::assertStringContainsString('d-none', $popover->getAttribute('class'));
        self::assertSame('button', $button->getAttribute('type'));
    }

    /**
     * The popover hangs below the button, aligned to its end, and stays above the chart.
     */
    #[Test]
    public function positionsThePopoverBelowTheButtonAboveTheChart(): void
    {
        $xpath   = ViewFixture::xpathOf($this->render());
        $popover = ViewFixture::first($xpath, '//div[@id="my-help"]');
        $classes = explode(' ', $popover->getAttribute('class'));

        foreach (['position-absolute', 'top-100', 'end-0', 'mt-2'] as $class) {
            self::assertContains($class, $classes);
        }

        self::assertStringContainsString('z-index: 1000', $popover->getAttribute('style'));
    }

    /**
     * A narrow viewport must not push the popover out of the screen, so the width is bound by the
     * viewport and not a fixed minimum.
     */
    #[Test]
    public function boundsTheWidthByTheViewport(): void
    {
        $xpath   = ViewFixture::xpathOf($this->render());
        $popover = ViewFixture::first($xpath, '//div[@id="my-help"]');
        $style   = $popover->getAttribute('style');

        self::assertStringContainsString('min-width: min(350px, calc(100vw - 2rem))', $style);
        self::assertStringContainsString('max-width: calc(100vw - 2rem)', $style);
        self::assertStringNotContainsString('min-width: 350px', $style);
    }

    /**
     * Rows without a mode apply to every mode and start visible. Rows of the active mode start
     * visible too, rows of another mode start hidden and the page script switches them.
     */
    #[Test]
    public function marksTheRowsOfEachMode(): void
    {
        $xpath = ViewFixture::xpathOf($this->render());

        $tooltip = ViewFixture::elements($xpath, '//tr[@data-help-mode="tooltip"]');
        $panel   = ViewFixture::elements($xpath, '//tr[@data-help-mode="panel"]');
        $both    = ViewFixture::elements($xpath, '//tr[not(@data-help-mode)]');

        self::assertCount(1, $tooltip);
        self::assertCount(1, $panel);
        self::assertCount(2, $both);

        self::assertStringNotContainsString('d-none', $tooltip[0]->getAttribute('class'));
        self::assertStringContainsString('d-none', $panel[0]->getAttribute('class'));

        foreach ($both as $row) {
            self::assertStringNotContainsString('d-none', $row->getAttribute('class'));
        }

        self::assertSame(
            ['Tab key', 'Ctrl + scroll'],
            array_map(
                static fn (DOMElement $key): string => trim($key->textContent),
                ViewFixture::elements($xpath, '//tr[not(@data-help-mode)]/td[1]/strong'),
            ),
        );
    }

    /**
     * The active mode decides which moded rows start visible.
     */
    #[Test]
    public function showsTheRowsOfTheActiveMode(): void
    {
        $xpath = ViewFixture::xpathOf($this->render('panel'));

        self::assertStringContainsString(
            'd-none',
            ViewFixture::first($xpath, '//tr[@data-help-mode="tooltip"]')->getAttribute('class')
        );
        self::assertStringNotContainsString(
            'd-none',
            ViewFixture::first($xpath, '//tr[@data-help-mode="panel"]')->getAttribute('class')
        );
    }

    /**
     * Without an active mode every row of a mode starts hidden and the rows without a mode stay visible.
     */
    #[Test]
    public function hidesEveryModedRowWithoutAnActiveMode(): void
    {
        $xpath = ViewFixture::xpathOf($this->render(null));

        foreach (ViewFixture::elements($xpath, '//tr[@data-help-mode]') as $row) {
            self::assertStringContainsString('d-none', $row->getAttribute('class'));
        }

        foreach (ViewFixture::elements($xpath, '//tr[not(@data-help-mode)]') as $row) {
            self::assertSame('', $row->getAttribute('class'));
        }
    }

    /**
     * The texts come translated from the module and can hold a quote, an ampersand or a tag. The
     * label, the id and the rows are all escaped and stay one value or one text each.
     */
    #[Test]
    public function escapesTheLabelTheIdAndTheRows(): void
    {
        $text  = 'Say "help" & <b>more</b>';
        $xpath = ViewFixture::xpathOf(ViewFixture::render('help-popover', [
            'id'         => 'a"b',
            'label'      => $text,
            'rows'       => [['key' => $text . ' key', 'text' => $text . ' text', 'mode' => 'm"x']],
            'activeMode' => 'm"x',
        ]));

        $popover = ViewFixture::first($xpath, '//div[contains(concat(" ", @class, " "), " help-popover ")]');

        self::assertSame($text, ViewFixture::first($xpath, '//button')->getAttribute('title'));
        self::assertSame($text, $popover->getAttribute('aria-label'));
        self::assertSame('a"b', $popover->getAttribute('id'));
        self::assertSame('a"b', ViewFixture::first($xpath, '//button')->getAttribute('aria-controls'));
        self::assertSame('m"x', ViewFixture::first($xpath, '//tr')->getAttribute('data-help-mode'));
        self::assertSame($text . ' key', ViewFixture::first($xpath, '//tr/td[1]/strong')->textContent);
        self::assertSame($text . ' text', ViewFixture::first($xpath, '//tr/td[2]')->textContent);
        self::assertSame([], ViewFixture::elements($xpath, '//b'));
    }

    /**
     * The toggle lives in the page script, so the markup carries no inline click handler that
     * would toggle the popover a second time.
     */
    #[Test]
    public function carriesNoInlineClickHandler(): void
    {
        $xpath = ViewFixture::xpathOf($this->render());

        self::assertSame([], ViewFixture::elements($xpath, '//*[@onclick]'));
    }

    /**
     * Renders the popover with the rows of a chart that has a tooltip and a panel mode.
     */
    private function render(?string $activeMode = 'tooltip'): string
    {
        return ViewFixture::render('help-popover', [
            'id'         => 'my-help',
            'label'      => 'Show help',
            'rows'       => self::ROWS,
            'activeMode' => $activeMode,
        ]);
    }
}
