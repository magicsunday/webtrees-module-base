<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Catalogue;

use function mb_check_encoding;
use function preg_replace;
use function str_replace;

/**
 * Makes text from a catalogue safe to write to a terminal or a CI log.
 *
 * A catalogue can come from a contributor, and its texts are reported back to whoever runs
 * the resolver. A control character in such a text could recolour or overwrite lines of the
 * report, or let a log line start a workflow command of the CI system. The control
 * characters of both ranges count, the ASCII ones and the ones a terminal reads as the
 * eight-bit form of an escape sequence. So do the characters that set the direction of a text,
 * since they could show a line in another order than it is written. The opening of the older workflow command form is
 * broken up as well, since a CI system may look for it anywhere in a line.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class PrintableText
{
    /**
     * Not meant to be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Replaces the control characters of both ranges and the characters that set the direction of a
     * text, the line feed included but not the tab, and breaks up the opening of the older workflow
     * command form.
     *
     * The result holds no line feed, carriage return or next-line character, so it can never start a
     * line of its own in a report. The other Unicode line and paragraph separators stay. Text that is
     * not valid UTF-8 cannot be judged character by character, so only printable ASCII and the tab
     * stay in it.
     *
     * @param string $text The text to write out on one line
     *
     * @return string The text with each such control character replaced by a question mark and no `##[` opening
     */
    public static function line(string $text): string
    {
        $clean = mb_check_encoding($text, 'UTF-8')
            ? preg_replace('/[\x00-\x08\x0A-\x1F\x7F\x{0080}-\x{009F}\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '?', $text)
            : preg_replace('/[^\x20-\x7E\t]/', '?', $text);

        return str_replace('##[', '##?[', $clean ?? '?');
    }
}
