<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Source;

use Fisharebest\Webtrees\I18N;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function file_get_contents;
use function in_array;
use function is_array;
use function sprintf;
use function str_replace;
use function strtolower;
use function token_get_all;

use const DIRECTORY_SEPARATOR;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_DOUBLE_COLON;
use const T_STRING;
use const T_WHITESPACE;

/**
 * The library carries no translated text. It has no catalogue, and the catalogue pipeline of a
 * consuming module extracts only from its own sources, so a text translated in here would be in
 * no catalogue and would show in English in every other language. A consumer supplies its texts
 * from its own call sites, where xgettext finds them.
 *
 * This guard scans the code of `src` for a static call of the three webtrees translation methods
 * and has no exception. It reads tokens, so a comment or a string that names such a call does not
 * count, and it matches the method name whatever the class is called, so an alias of the class
 * does not hide a call. The scanner is pinned on sample code as well, because a scan that finds
 * nothing is only a guard once it is shown to find something.
 *
 * This test has no subject under `src`, so it sits outside the mirror of the source tree.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversNothing]
final class TranslationFreeSourceTest extends TestCase
{
    /**
     * The webtrees translation methods, in lower case because PHP method names are not case sensitive.
     */
    private const array TRANSLATION_METHODS = [
        'translate',
        'plural',
        'translatecontext',
    ];

    /**
     * @return array<string, array{string, int}>
     */
    public static function sourceCodeDataProvider(): array
    {
        // [ PHP code, number of translation calls the scanner must report ]
        return [
            'a call on the webtrees class'           => ["<?php I18N::translate('x');", 1],
            'a call on the fully qualified class'    => ['<?php \\' . I18N::class . "::plural('a', 'b', 1);", 1],
            'a call with a context'                  => ["<?php I18N::translateContext('c', 'x');", 1],
            'a call on an alias of the class'        => ["<?php Translator::translate('x');", 1],
            'a first class callable'                 => ['<?php $call = I18N::translate(...);', 1],
            'a method name in other letter case'     => ["<?php I18N::TRANSLATE('x');", 1],
            'a space after the scope operator'       => ["<?php I18N:: translate('x');", 1],
            'a line break after the scope operator'  => ["<?php I18N::\n    translate('x');", 1],
            'a comment after the scope operator'     => ["<?php I18N::/* c */translate('x');", 1],
            'another method after a space'           => ['<?php I18N:: languageTag();', 0],
            'two calls on one line are two findings' => ["<?php I18N::translate('a'); I18N::plural('a', 'b', 1);", 2],
            'a line comment that names a call'       => ['<?php // I18N::translate() is called by the consumer', 0],
            'a docblock that names a call'           => ["<?php /**\n * Uses I18N::translate() at its call site.\n */", 0],
            'a string that names a call'             => ["<?php \$text = 'I18N::translate';", 0],
            'another static method of the class'     => ['<?php I18N::languageTag(); I18N::scriptDirection($x);', 0],
            'an instance method called translate'    => ["<?php \$translator->translate('x');", 0],
        ];
    }

    /**
     * The scanner reports a static call of a translation method wherever it is written in code
     * and nothing that only names one in a comment or a string.
     *
     * @param string $code     The PHP code to scan
     * @param int    $expected The number of translation calls it must report
     *
     * @return void
     */
    #[Test]
    #[DataProvider('sourceCodeDataProvider')]
    public function findsTheTranslationCallsInCodeAndNothingElse(string $code, int $expected): void
    {
        self::assertCount($expected, $this->translationCallsIn($code));
    }

    /**
     * No file of `src` calls a webtrees translation method.
     *
     * @return void
     */
    #[Test]
    public function theSourceOfTheLibraryTranslatesNothing(): void
    {
        $sourceRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src';
        $findings   = [];
        $scanned    = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $code = file_get_contents($file->getPathname());

            self::assertIsString($code, 'a source file must be readable');

            $relativePath = str_replace($sourceRoot . DIRECTORY_SEPARATOR, 'src/', $file->getPathname());
            $scanned[]    = $relativePath;

            foreach ($this->translationCallsIn($code) as [$line, $method]) {
                $findings[] = sprintf('%s:%d calls %s', $relativePath, $line, $method);
            }
        }

        // An empty set of files would pass the guard without looking at anything, so a file that
        // is known to be there has to be among those scanned.
        self::assertContains('src/Processor/DateProcessor.php', $scanned, 'the scan must visit the source files');

        self::assertSame(
            [],
            $findings,
            'The library carries no translated text, the consumer passes it in from its own call site.',
        );
    }

    /**
     * Returns the static calls of a translation method in the code, in the order they are written.
     *
     * @param string $code The PHP code to scan
     *
     * @return list<array{int, string}> The line number and the called method name of every call
     */
    private function translationCallsIn(string $code): array
    {
        $calls  = [];
        $tokens = token_get_all($code);

        foreach ($tokens as $index => $token) {
            if (!is_array($token) || ($token[0] !== T_DOUBLE_COLON)) {
                continue;
            }

            // PHP allows whitespace and comments between the scope operator and the method name.
            $position = $index + 1;

            while (
                is_array($tokens[$position] ?? null)
                && in_array($tokens[$position][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ) {
                ++$position;
            }

            $next = $tokens[$position] ?? null;

            if (
                is_array($next)
                && ($next[0] === T_STRING)
                && in_array(strtolower($next[1]), self::TRANSLATION_METHODS, true)
            ) {
                $calls[] = [$next[2], $next[1]];
            }
        }

        return $calls;
    }
}
