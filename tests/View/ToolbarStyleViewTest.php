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

use function count;
use function explode;
use function str_ends_with;
use function str_replace;
use function substr_count;
use function trim;

/**
 * Renders the toolbar style view that the chart modules share.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversNothing]
final class ToolbarStyleViewTest extends TestCase
{
    /**
     * The selectors of the rules, relative to the container class.
     */
    private const array SELECTORS = [
        '.btn-chart',
        '.btn-chart.btn-primary:hover',
        '.btn-chart:hover',
        '.btn-chart:focus-visible',
        '.btn-chart .icon',
        '.btn-chart .icon .svg-inline--fa',
    ];

    /**
     * The selectors of the rules, relative to the container class.
     *
     * @return iterable<string, array{string}>
     */
    public static function selectors(): iterable
    {
        foreach (self::SELECTORS as $selector) {
            yield $selector => [$selector];
        }
    }

    /**
     * The view writes one style element and nothing else.
     */
    #[Test]
    public function writesOneStyleElementAndNothingElse(): void
    {
        $html  = $this->render('my-chart');
        $xpath = ViewFixture::xpathOf($html);

        self::assertCount(1, ViewFixture::elements($xpath, '//style'));
        self::assertSame(1, substr_count($html, '<style'));
        self::assertStringStartsWith('<style>', trim($html));
        self::assertStringEndsWith('</style>', trim($html));
    }

    /**
     * Every rule is scoped by the container class and appears once.
     *
     * @param string $selector The selector relative to the container class
     */
    #[Test]
    #[DataProvider('selectors')]
    public function scopesEveryRuleByTheContainerClassOnce(string $selector): void
    {
        $css = $this->styleOf('my-chart');

        self::assertSame(1, substr_count($css, ".my-chart $selector {"));
    }

    /**
     * No rule starts without the container class.
     */
    #[Test]
    public function leavesNoRuleUnscoped(): void
    {
        $css = $this->styleOf('my-chart');

        self::assertSame(count(self::SELECTORS), substr_count($css, '{'));
        self::assertSame(count(self::SELECTORS), substr_count($css, '.my-chart .'));
    }

    /**
     * Two renders with different containers each carry their own container class only.
     */
    #[Test]
    public function usesTheContainerOfEachRender(): void
    {
        $first  = $this->styleOf('first-chart');
        $second = $this->styleOf('second-chart');

        self::assertStringContainsString('.first-chart .btn-chart {', $first);
        self::assertStringNotContainsString('second-chart', $first);
        self::assertStringContainsString('.second-chart .btn-chart {', $second);
        self::assertStringNotContainsString('first-chart', $second);
    }

    /**
     * A container string with selector syntax cannot add a rule or leave the selector, and a
     * container without an allowed character renders nothing.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function hostileContainers(): iterable
    {
        yield 'rule break out' => ['x {} body {color:red} .y', 'xbodycolorredy'];
        yield 'style close' => ['x</style><script>alert(1)</script>', 'xstylescriptalert1script'];
        yield 'comma list' => ['a, body', 'abody'];
        yield 'dot and space' => ['.a .b', 'ab'];
        yield 'brace only' => ['{}', ''];
        yield 'empty' => ['', ''];
        yield 'no allowed character' => ['. #', ''];
        yield 'mixed case underscore digit hyphen' => ['My_Chart-2', 'My_Chart-2'];
    }

    /**
     * Only letters, digits, underscore and hyphen reach the selector, and those pass unchanged.
     *
     * @param string $container The container string handed to the view
     * @param string $expected  The class name that reaches the selector
     */
    #[Test]
    #[DataProvider('hostileContainers')]
    public function sanitisesTheContainer(string $container, string $expected): void
    {
        $html = $this->render($container);

        if ($expected === '') {
            self::assertSame('', trim($html));

            return;
        }

        $css = $this->styleOf($container);

        self::assertSame(count(self::SELECTORS), substr_count($css, '{'));
        self::assertSame(count(self::SELECTORS), substr_count($css, '}'));
        self::assertSame(count(self::SELECTORS), substr_count($css, ".$expected ."));
        self::assertSame(1, substr_count($html, '<style'));
        self::assertSame(1, substr_count($html, '</style>'));
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('body', $this->selectorsOf($css, $expected));
    }

    /**
     * The selector lines of the stylesheet with the expected class removed, so a test can look for strays.
     *
     * @param string $css      The text of the style element
     * @param string $expected The class name that reaches the selector
     *
     * @return string The selector lines without the container class, joined
     */
    private function selectorsOf(string $css, string $expected): string
    {
        $selectors = '';

        foreach (explode("\n", $css) as $line) {
            if (str_ends_with($line, '{')) {
                $selectors .= str_replace('.' . $expected . ' ', '', $line);
            }
        }

        return $selectors;
    }

    /**
     * Renders the view for a container class.
     *
     * @param string $container The container string handed to the view
     *
     * @return string The rendered HTML
     */
    private function render(string $container): string
    {
        return ViewFixture::render('toolbar-style', ['container' => $container]);
    }

    /**
     * Returns the text of the style element for a container class.
     *
     * @param string $container The container string handed to the view
     *
     * @return string The text of the style element
     */
    private function styleOf(string $container): string
    {
        $xpath = ViewFixture::xpathOf($this->render($container));

        return ViewFixture::first($xpath, '//style')->textContent;
    }
}
