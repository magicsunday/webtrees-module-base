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
use function ltrim;
use function substr_count;
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
     * The rows keep the order of the list, also within one mode, and the markup keeps the classes
     * of the button, the icon, the popover and the table.
     */
    #[Test]
    public function keepsTheOrderOfTheRowsAndTheClassesOfTheParts(): void
    {
        $xpath = ViewFixture::xpathOf(ViewFixture::render('help-popover', [
            'id'    => 'my-help',
            'label' => 'Show help',
            'rows'  => [
                ['key' => 'First', 'text' => 'one', 'mode' => 'a'],
                ['key' => 'Second', 'text' => 'two', 'mode' => 'a'],
                ['key' => 'Third', 'text' => 'three'],
            ],
            'activeMode' => 'a',
        ]));

        self::assertSame(
            ['First', 'Second', 'Third'],
            array_map(
                static fn (DOMElement $key): string => trim($key->textContent),
                ViewFixture::elements($xpath, '//tr/td[1]/strong'),
            ),
        );
        self::assertSame(
            ['one', 'two', 'three'],
            array_map(
                static fn (DOMElement $text): string => trim($text->textContent),
                ViewFixture::elements($xpath, '//tr/td[2]'),
            ),
        );

        self::assertSame('btn-primary btn-chart btn-help', ViewFixture::first($xpath, '//button')->getAttribute('class'));
        self::assertCount(1, ViewFixture::elements($xpath, '//button/span[@class="icon"]/svg'));

        $icon = ViewFixture::first($xpath, '//button/span[@class="icon"]/svg');

        self::assertSame('svg-inline--fa fa-fw', $icon->getAttribute('class'));
        self::assertSame('0 0 320 512', $icon->getAttribute('viewbox'));

        $path = ViewFixture::first($xpath, '//button//svg/path');

        self::assertSame('currentColor', $path->getAttribute('fill'));
        self::assertStringStartsWith('M80 160c0-35.3', $path->getAttribute('d'));
        self::assertSame('position-relative d-inline-block', ViewFixture::first($xpath, '/html/body/div')->getAttribute('class'));
        self::assertSame(
            'help-popover d-none position-absolute top-100 end-0 mt-2 p-3 bg-white border rounded shadow-sm',
            ViewFixture::first($xpath, '//div[@id="my-help"]')->getAttribute('class')
        );
        self::assertSame('table table-sm table-borderless mb-0', ViewFixture::first($xpath, '//div[@id="my-help"]/table')->getAttribute('class'));
    }

    /**
     * The popover hangs below the button, aligned to its end.
     */
    #[Test]
    public function positionsThePopoverBelowTheButton(): void
    {
        $xpath   = ViewFixture::xpathOf($this->render());
        $popover = ViewFixture::first($xpath, '//div[@id="my-help"]');
        $classes = explode(' ', $popover->getAttribute('class'));

        foreach (['position-absolute', 'top-100', 'end-0', 'mt-2'] as $class) {
            self::assertContains($class, $classes);
        }
    }

    /**
     * The size and the stacking order of the popover come from one style element of the view for the
     * class `help-popover`, so the element carries no inline style, which a stylesheet could only
     * override with an important flag.
     */
    #[Test]
    public function carriesTheRulesOfThePopoverOnce(): void
    {
        $html    = $this->render();
        $xpath   = ViewFixture::xpathOf($html);
        $popover = ViewFixture::first($xpath, '//div[@id="my-help"]');
        $classes = explode(' ', $popover->getAttribute('class'));

        self::assertContains('help-popover', $classes);
        self::assertFalse($popover->hasAttribute('style'));
        self::assertStringNotContainsString('style=', $html);
        self::assertSame(1, substr_count($html, '<style'));
        self::assertCount(1, ViewFixture::elements($xpath, '//style'));
        self::assertSame(
            ".help-popover {\n"
            . "    min-width: min(400px, calc(100vw - 2rem));\n"
            . "    max-width: calc(100vw - 2rem);\n"
            . "    z-index: 1000;\n"
            . "}\n",
            ltrim(ViewFixture::first($xpath, '//style')->textContent, "\n")
        );
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
