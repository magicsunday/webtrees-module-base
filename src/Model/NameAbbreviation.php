<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Model;

/**
 * Name abbreviation strategy used by chart modules when a name does not fit the
 * available width. Resolves the AUTO setting against a tree's surname tradition
 * so the JS layer always receives a concrete GIVEN or SURNAME value.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class NameAbbreviation
{
    /**
     * Use the tree's surname tradition to pick GIVEN or SURNAME automatically.
     */
    public const string AUTO = 'AUTO';

    /**
     * Abbreviate given names first (default for most traditions).
     */
    public const string GIVEN = 'GIVEN';

    /**
     * Abbreviate surnames first (matches Icelandic patronymic usage).
     */
    public const string SURNAME = 'SURNAME';

    /**
     * All valid configuration values, in display order.
     *
     * @var list<string>
     */
    public const array CHOICES = [self::AUTO, self::GIVEN, self::SURNAME];

    /**
     * Resolves a configured strategy against a tree's surname tradition. AUTO
     * maps to SURNAME for Icelandic-tradition trees (where surnames are
     * typically patronymics and people are addressed by given name) and GIVEN
     * for everything else. Concrete values pass through unchanged.
     *
     * @param string $configured       One of self::AUTO, self::GIVEN, self::SURNAME
     * @param string $surnameTradition The tree's SURNAME_TRADITION preference value
     *
     * @return string Either self::GIVEN or self::SURNAME
     */
    public static function resolve(string $configured, string $surnameTradition): string
    {
        if ($configured !== self::AUTO) {
            return $configured;
        }

        return $surnameTradition === 'icelandic'
            ? self::SURNAME
            : self::GIVEN;
    }
}
