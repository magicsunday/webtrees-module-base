<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Catalogue;

use MagicSunday\Webtrees\ModuleBase\Catalogue\PrintableText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pins which characters of a catalogue text reach a report.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(PrintableText::class)]
final class PrintableTextTest extends TestCase
{
    /**
     * Provides a text and what the report shows of it. The rows cover the escape character,
     * a carriage return, the control characters at both ends of the replaced ranges, the
     * delete character, the line feed and the tab that stays.
     *
     * @return array<string, array{string, string}> The rows, keyed by a description of the case, each with the text and the expected result
     */
    public static function texts(): array
    {
        return [
            'escape'                                          => ["a\x1b[31mb", 'a?[31mb'],
            'carriage return'                                 => ["a\rb", 'a?b'],
            'first control character'                         => ["a\x00b", 'a?b'],
            'backspace'                                       => ["a\x08b", 'a?b'],
            'vertical tab'                                    => ["a\x0bb", 'a?b'],
            'last control character'                          => ["a\x1fb", 'a?b'],
            'delete character'                                => ["a\x7fb", 'a?b'],
            'first eight-bit control'                         => ["a\u{0080}b", 'a?b'],
            'eight-bit csi'                                   => ["a\u{009B}2Jb", 'a?2Jb'],
            'eight-bit osc'                                   => ["a\u{009D}0;xb", 'a?0;xb'],
            'last eight-bit control'                          => ["a\u{009F}b", 'a?b'],
            'no-break space stays'                            => ["a\u{00A0}b", "a\u{00A0}b"],
            'right-to-left override'                          => ["a\u{202E}b", 'a?b'],
            'left-to-right mark'                              => ["a\u{200E}b", 'a?b'],
            'right-to-left mark'                              => ["a\u{200F}b", 'a?b'],
            'first embedding control'                         => ["a\u{202A}b", 'a?b'],
            'first isolate control'                           => ["a\u{2066}b", 'a?b'],
            'last isolate control'                            => ["a\u{2069}b", 'a?b'],
            'invalid utf-8 keeps ascii'                       => ["a\xFF\x1bb\xC3\xA4", 'a??b??'],
            'older workflow command opening'                  => ['a##[error]b', 'a##?[error]b'],
            'older workflow command opening in invalid utf-8' => ["a##[error]b\xFF", 'a##?[error]b?'],
            'invalid utf-8 keeps the tab'                     => ["a\nb\t\xFF", "a?b\t?"],
            'line feed'                                       => ["a\nb", 'a?b'],
            'line feed before a command'                      => ["x\n::error::y", 'x?::error::y'],
            'tab stays'                                       => ["a\tb", "a\tb"],
            'space stays'                                     => ['a b', 'a b'],
            'umlauts stay'                                    => ['Größe — äöü', 'Größe — äöü'],
        ];
    }

    /**
     * A control character and the line feed are replaced and every other character, the tab included, stays.
     *
     * @param string $text     The text to write out
     * @param string $expected What the report shows of it
     */
    #[Test]
    #[DataProvider('texts')]
    public function replacesControlCharactersOnly(string $text, string $expected): void
    {
        self::assertSame($expected, PrintableText::line($text));
    }
}
