<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Support;

use IntlDateFormatter;
use Throwable;

use function extension_loaded;
use function str_contains;
use function str_repeat;
use function str_replace;
use function strlen;

/**
 * Derives a locale-aware, compact (numeric) date format string for webtrees'
 * AbstractCalendarDate::format() from the CLDR/ICU short-date pattern of a locale.
 *
 * Only the pattern (field order and separators) is taken from ICU; the actual date
 * is still rendered by webtrees, so non-Gregorian calendars, B.C.E. years and native
 * digit shaping keep working. The year is always widened to four digits, since the
 * ICU short pattern frequently uses a two-digit year that would be ambiguous for
 * historical dates.
 *
 * Examples: de → "%d.%m.%Y", en-US → "%n/%j/%Y", fr → "%d/%m/%Y", ja → "%Y/%m/%d",
 * sv → "%Y-%m-%d".
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class CompactDateFormat
{
    /**
     * The fallback compact format (German numeric order) used when ICU is unavailable
     * or produces a pattern that does not carry a full day/month/year triple.
     */
    public const string FALLBACK = '%d.%m.%Y';

    /**
     * Static-only utility.
     */
    private function __construct()
    {
    }

    /**
     * Returns the webtrees compact date format string for the given BCP-47 locale tag.
     *
     * @param string $localeTag The active locale tag (e.g. "de", "en-US")
     *
     * @return string
     */
    public static function forLocale(string $localeTag): string
    {
        if (!extension_loaded('intl')) {
            return self::FALLBACK;
        }

        try {
            $formatter = new IntlDateFormatter(
                str_replace('-', '_', $localeTag),
                IntlDateFormatter::SHORT,
                IntlDateFormatter::NONE,
                'UTC',
                IntlDateFormatter::GREGORIAN
            );

            $pattern = $formatter->getPattern();
        } catch (Throwable) {
            // ext-intl variance for an unparseable locale (never emitted by webtrees
            // at runtime): PHP 8.5 throws a ValueError from the constructor, while PHP
            // 8.3/8.4 leave an unconstructed formatter whose getPattern() raises an
            // Error. Either way, fall back to the German numeric format.
            return self::FALLBACK;
        }

        if (($pattern === false) || ($pattern === '')) {
            return self::FALLBACK;
        }

        $format = self::icuToWebtrees($pattern);

        // Require a complete day/month/year triple; otherwise fall back rather than
        // ship a partial format for an unexpected locale pattern.
        $hasYear  = str_contains($format, '%Y');
        $hasMonth = str_contains($format, '%m') || str_contains($format, '%n');
        $hasDay   = str_contains($format, '%d') || str_contains($format, '%j');

        if (!$hasYear || !$hasMonth || !$hasDay) {
            return self::FALLBACK;
        }

        return $format;
    }

    /**
     * Translates a CLDR/ICU short-date pattern into a webtrees calendar format string.
     *
     * ICU field letters map as: any run of y→%Y (webtrees' four-digit long year,
     * so a two-digit ICU short year is widened here too), MM→%m / M→%n, dd→%d /
     * d→%j. Quoted literals and separators are passed through unchanged.
     *
     * @param string $pattern The ICU short-date pattern (e.g. "dd.MM.yy")
     *
     * @return string
     */
    private static function icuToWebtrees(string $pattern): string
    {
        $out     = '';
        $length  = strlen($pattern);
        $index   = 0;
        $literal = false;

        while ($index < $length) {
            $char = $pattern[$index];

            // ICU quotes literal text in single quotes; '' is an escaped apostrophe.
            if ($char === "'") {
                if ((($index + 1) < $length) && ($pattern[$index + 1] === "'")) {
                    $out .= "'";
                    $index += 2;

                    continue;
                }

                $literal = !$literal;
                ++$index;

                continue;
            }

            if ($literal) {
                $out .= $char;
                ++$index;

                continue;
            }

            // Collect a run of the same pattern letter.
            $run = 1;

            while ((($index + $run) < $length) && ($pattern[$index + $run] === $char)) {
                ++$run;
            }

            $out .= match (true) {
                $char === 'y'                  => '%Y',
                ($char === 'M') && ($run >= 2) => '%m',
                $char === 'M'                  => '%n',
                ($char === 'd') && ($run >= 2) => '%d',
                $char === 'd'                  => '%j',
                default                        => str_repeat($char, $run),
            };

            $index += $run;
        }

        return $out;
    }
}
