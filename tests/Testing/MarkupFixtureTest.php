<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Testing;

use DOMElement;
use MagicSunday\Webtrees\ModuleBase\Testing\MarkupFixture;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function restore_error_handler;
use function set_error_handler;

/**
 * Pins how the markup fixture parses markup and finds elements in it.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(MarkupFixture::class)]
final class MarkupFixtureTest extends TestCase
{
    /**
     * Markup with two list items and a paragraph with an umlaut.
     */
    private const string MARKUP = '<ul><li class="a">One</li><li class="b">Zwei</li></ul><p id="p">Größe</p>';

    /**
     * The markup is parsed as UTF-8, so an umlaut keeps its form.
     *
     * @return void
     */
    #[Test]
    public function parsesTheMarkupAsUtf8(): void
    {
        $paragraph = MarkupFixture::first(MarkupFixture::xpathOf(self::MARKUP), '//p');

        self::assertSame('Größe', $paragraph->textContent);
    }

    /**
     * Markup with a tag that the parser does not know is parsed without raising a warning. The parser
     * keeps no error behind and does not leave its error mode changed for the caller.
     *
     * @return void
     */
    #[Test]
    public function parsesMarkupWithAnUnknownTagWithoutAWarningAndLeavesNoErrorBehind(): void
    {
        $before = libxml_use_internal_errors(false);
        libxml_clear_errors();
        $caught = [];

        set_error_handler(static function (int $level, string $message) use (&$caught): bool {
            $caught[] = $message;

            return true;
        });

        try {
            $xpath  = MarkupFixture::xpathOf('<div><foo>open');
            $after  = libxml_use_internal_errors($before);
            $errors = libxml_get_errors();
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $caught);
        self::assertFalse($after);
        self::assertSame([], $errors);
        self::assertSame('open', MarkupFixture::first($xpath, '//foo')->textContent);
    }

    /**
     * A caller that collects the parser errors itself keeps its error mode, and the errors of the
     * markup do not end up in its list.
     *
     * @return void
     */
    #[Test]
    public function leavesTheErrorListOfACallerThatCollectsErrorsEmpty(): void
    {
        $before = libxml_use_internal_errors(true);
        libxml_clear_errors();

        MarkupFixture::xpathOf('<div><foo>open');

        $errors = libxml_get_errors();
        $after  = libxml_use_internal_errors($before);

        self::assertSame([], $errors);
        self::assertTrue($after);
    }

    /**
     * The elements are returned in document order.
     *
     * @return void
     */
    #[Test]
    public function returnsTheElementsThatTheExpressionFindsInDocumentOrder(): void
    {
        $elements = MarkupFixture::elements(MarkupFixture::xpathOf(self::MARKUP), '//li');

        self::assertSame(['One', 'Zwei'], array_map(static fn (DOMElement $element): string => $element->textContent, $elements));
    }

    /**
     * An expression that finds only text, not elements, gives no element.
     *
     * @return void
     */
    #[Test]
    public function leavesOutNodesThatAreNoElements(): void
    {
        self::assertSame([], MarkupFixture::elements(MarkupFixture::xpathOf(self::MARKUP), '//li/text()'));
    }

    /**
     * An expression that cannot be evaluated finds no element and raises no further warning than the
     * one of the query itself.
     *
     * @return void
     */
    #[Test]
    public function findsNothingForAnExpressionThatCannotBeEvaluated(): void
    {
        $caught = [];

        set_error_handler(static function (int $level, string $message) use (&$caught): bool {
            $caught[] = $message;

            return true;
        });

        try {
            $elements = MarkupFixture::elements(MarkupFixture::xpathOf(self::MARKUP), '//li[');
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $elements);
        self::assertCount(1, $caught);
    }

    /**
     * The first element is the first one in document order.
     *
     * @return void
     */
    #[Test]
    public function returnsTheFirstElementThatTheExpressionFinds(): void
    {
        self::assertSame('One', MarkupFixture::first(MarkupFixture::xpathOf(self::MARKUP), '//li')->textContent);
    }

    /**
     * Asking for an element that is not there fails the test with a message that names the expression.
     *
     * @return void
     */
    #[Test]
    public function failsWhenTheExpressionFindsNoElement(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/^The markup has no element for \/\/table$/');

        MarkupFixture::first(MarkupFixture::xpathOf(self::MARKUP), '//table');
    }
}
