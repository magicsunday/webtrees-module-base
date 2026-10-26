<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Support;

use MagicSunday\Webtrees\ModuleBase\Support\PlainText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * PlainTextTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(PlainText::class)]
final class PlainTextTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function markupProvider(): array
    {
        // [ webtrees markup, expected plain text ]
        return [
            'plain text is unchanged'        => ['Berlin', 'Berlin'],
            'tags are removed'               => ['<span class="date">12 MAY 1832</span>', '12 MAY 1832'],
            'entities are decoded'           => ['Smith &amp; Sons', 'Smith & Sons'],
            'quote entities are decoded'     => ['O&#039;Brien &quot;Bob&quot;', 'O\'Brien "Bob"'],
            'tags and entities together'     => ['<a href="#">A &lt; B</a>', 'A < B'],
            'multibyte characters are kept'  => ['Müller &amp; Söhne', 'Müller & Söhne'],
            'an escaped tag stays literal'   => ['Research &lt;draft&gt; &amp; development', 'Research <draft> & development'],
            'empty input gives empty output' => ['', ''],
        ];
    }

    /**
     * Webtrees markup is reduced to plain text, the tags are removed and the entities are decoded.
     *
     * @param string $markup   The webtrees markup
     * @param string $expected The expected plain text
     *
     * @return void
     */
    #[Test]
    #[DataProvider('markupProvider')]
    public function fromMarkupReducesWebtreesMarkupToPlainText(string $markup, string $expected): void
    {
        self::assertSame($expected, PlainText::fromMarkup($markup));
    }
}
