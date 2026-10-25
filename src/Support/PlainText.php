<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Support;

use function html_entity_decode;
use function strip_tags;

use const ENT_QUOTES;

/**
 * Reduces markup produced by webtrees, such as the output of a date display or a fact
 * value, to the plain text a chart payload carries.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class PlainText
{
    /**
     * Static-only utility.
     */
    private function __construct()
    {
    }

    /**
     * Strips the tags and decodes the entities of the given markup.
     *
     * The result is plain text, so a character such as "<" or "&" may appear in it. A
     * caller that writes it into an HTML context has to escape it there.
     *
     * @param string $markup The markup to reduce to plain text
     *
     * @return string
     */
    public static function fromMarkup(string $markup): string
    {
        return html_entity_decode(strip_tags($markup), ENT_QUOTES, 'UTF-8');
    }
}
