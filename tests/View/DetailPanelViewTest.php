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
use function substr_count;

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
     *
     * @param string $part The value of the data-panel attribute that the script looks up
     */
    #[Test]
    #[DataProvider('panelParts')]
    public function carriesEveryPartOnce(string $part): void
    {
        $xpath = ViewFixture::xpathOf(ViewFixture::render('detail-panel', ViewFixture::DETAIL_PANEL_PARAMETERS));

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
        $xpath = ViewFixture::xpathOf(ViewFixture::render('detail-panel', ViewFixture::DETAIL_PANEL_PARAMETERS));
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
        $xpath = ViewFixture::xpathOf(ViewFixture::render('detail-panel', ViewFixture::DETAIL_PANEL_PARAMETERS));

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

        // The heading is found by the escaped id that the aside points to.
        self::assertSame('title', ViewFixture::first($xpath, "//*[@id='a\"b-title']")->getAttribute('data-panel'));
    }

    /**
     * The panel script and the shared panel styles depend on these details of the markup.
     */
    #[Test]
    public function keepsTheMarkupThatScriptAndStylesDependOn(): void
    {
        $xpath = ViewFixture::xpathOf(ViewFixture::render('detail-panel', ViewFixture::DETAIL_PANEL_PARAMETERS));

        self::assertSame('offcanvas', ViewFixture::first($xpath, '//button[contains(concat(" ", @class, " "), " btn-close ")]')->getAttribute('data-bs-dismiss'));
        self::assertSame('-1', ViewFixture::first($xpath, '//*[@data-panel="title"]')->getAttribute('tabindex'));

        $image = ViewFixture::first($xpath, '//*[@data-panel="image"]');

        self::assertSame('img', $image->tagName);
        self::assertSame('detail-image', $image->getAttribute('class'));
        self::assertTrue($image->hasAttribute('alt'));
        self::assertSame('', $image->getAttribute('alt'));

        $facts = ViewFixture::first($xpath, '//*[@data-panel="facts"]');

        self::assertSame('dl', $facts->tagName);
        self::assertContains('detail-facts', explode(' ', $facts->getAttribute('class')));

        $header = ViewFixture::first($xpath, '//aside/div[@class="offcanvas-header"]');
        $body   = ViewFixture::first($xpath, '//aside/div[@class="offcanvas-body"]');

        self::assertCount(1, ViewFixture::elements($xpath, '//div[@class="offcanvas-header"]//*[@data-panel="title"]'));
        self::assertCount(1, ViewFixture::elements($xpath, '//div[@class="offcanvas-header"]//button[@data-bs-dismiss]'));

        foreach (['image', 'hint', 'facts', 'empty', 'actions'] as $part) {
            self::assertCount(1, ViewFixture::elements($xpath, '//div[@class="offcanvas-body"]//*[@data-panel="' . $part . '"]'));
        }

        self::assertSame('div', $header->tagName);
        self::assertSame('div', $body->tagName);
    }

    /**
     * The actions sit inside their container, the buttons carry their type and classes, and the
     * title, hint, empty text and facts list keep the classes that the panel styles use.
     */
    #[Test]
    public function keepsTheActionsAndTheClassesOfTheParts(): void
    {
        $xpath = ViewFixture::xpathOf(ViewFixture::render('detail-panel', ViewFixture::DETAIL_PANEL_PARAMETERS));

        foreach (['individual', 'center'] as $action) {
            self::assertCount(1, ViewFixture::elements($xpath, '//*[@data-panel="actions"]//*[@data-panel="' . $action . '"]'));
        }

        self::assertSame('d-grid gap-2 mt-3', ViewFixture::first($xpath, '//*[@data-panel="actions"]')->getAttribute('class'));
        self::assertSame('offcanvas-title h5', ViewFixture::first($xpath, '//*[@data-panel="title"]')->getAttribute('class'));
        self::assertSame('h2', ViewFixture::first($xpath, '//*[@data-panel="title"]')->tagName);
        self::assertSame('text-muted', ViewFixture::first($xpath, '//*[@data-panel="hint"]')->getAttribute('class'));
        self::assertSame('text-muted', ViewFixture::first($xpath, '//*[@data-panel="empty"]')->getAttribute('class'));
        self::assertSame('detail-facts mb-0', ViewFixture::first($xpath, '//*[@data-panel="facts"]')->getAttribute('class'));
        self::assertSame('btn-close', ViewFixture::first($xpath, '//button[@data-bs-dismiss]')->getAttribute('class'));
        self::assertSame('button', ViewFixture::first($xpath, '//button[@data-bs-dismiss]')->getAttribute('type'));
        self::assertSame('button', ViewFixture::first($xpath, '//*[@data-panel="individual"]')->getAttribute('type'));
        self::assertSame('button', ViewFixture::first($xpath, '//*[@data-panel="center"]')->getAttribute('type'));
        self::assertSame('btn btn-primary btn-sm', ViewFixture::first($xpath, '//*[@data-panel="individual"]')->getAttribute('class'));
        self::assertSame('btn btn-outline-primary btn-sm', ViewFixture::first($xpath, '//*[@data-panel="center"]')->getAttribute('class'));
        self::assertSame('close', ViewFixture::first($xpath, '//button[@data-bs-dismiss]')->getAttribute('aria-label'));
    }

    /**
     * The shared rules of the panel, keyed by selector, each with its declarations.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function panelRules(): iterable
    {
        yield 'image' => [
            '.webtrees-detail-panel .detail-image',
            "    display: block;\n"
            . "    width: 9rem;\n"
            . "    height: 9rem;\n"
            . "    margin: 0 auto 1rem;\n"
            . "    border: 3px solid var(--bs-border-color, #dee2e6);\n"
            . "    border-radius: 50%;\n"
            . "    background-color: var(--bs-secondary-bg, #e9ecef);\n"
            . "    object-fit: contain;\n",
        ];

        yield 'hidden image' => ['.webtrees-detail-panel .detail-image[hidden]', "    display: none;\n"];

        yield 'fact term' => [
            '.webtrees-detail-panel .detail-facts dt',
            "    font-weight: 600;\n    margin-top: 0.75rem;\n",
        ];

        yield 'fact description' => ['.webtrees-detail-panel .detail-facts dd', "    margin-bottom: 0;\n"];
        yield 'place' => ['.webtrees-detail-panel .detail-place', "    font-style: italic;\n"];
        yield 'title focus' => ['.webtrees-detail-panel .offcanvas-title:focus', "    outline: none;\n"];
        yield 'shadow' => [
            '.webtrees-detail-panel.offcanvas-end',
            "    box-shadow: -0.75rem 0 1.5rem rgba(0, 0, 0, 0.25);\n",
        ];
        yield 'right to left shadow' => [
            '[dir="rtl"] .webtrees-detail-panel.offcanvas-end',
            "    box-shadow: 0.75rem 0 1.5rem rgba(0, 0, 0, 0.25);\n",
        ];
    }

    /**
     * The view writes the shared rules of the panel in one style element, once, and each rule has
     * the selector and the declarations of the chart library.
     *
     * @param string $selector     The selector of the rule
     * @param string $declarations The declarations of the rule, one per line
     */
    #[Test]
    #[DataProvider('panelRules')]
    public function carriesTheSharedRulesOfThePanelOnce(string $selector, string $declarations): void
    {
        $html  = ViewFixture::render('detail-panel', ViewFixture::DETAIL_PANEL_PARAMETERS);
        $xpath = ViewFixture::xpathOf($html);

        self::assertSame(1, substr_count($html, '<style'));
        self::assertCount(1, ViewFixture::elements($xpath, '//style'));
        self::assertSame(1, substr_count(ViewFixture::first($xpath, '//style')->textContent, $selector . " {\n" . $declarations . "}\n"));
    }

    /**
     * The comments of the rules stay with them, and the panel itself carries no inline style.
     */
    #[Test]
    public function keepsTheCommentsAndWritesNoInlineStyle(): void
    {
        $html = ViewFixture::render('detail-panel', ViewFixture::DETAIL_PANEL_PARAMETERS);
        $css  = ViewFixture::first(ViewFixture::xpathOf($html), '//style')->textContent;

        self::assertStringContainsString('A round image of fixed size with a ring around it.', $css);
        self::assertStringContainsString('The panel is an offcanvas that opens on a click on a person.', $css);
        self::assertStringContainsString('In a right-to-left page the panel sits at the left edge', $css);
        self::assertStringNotContainsString('style=', $html);
    }
}
