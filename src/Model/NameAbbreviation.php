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
 * available width. Resolves the Auto setting against a tree's surname tradition
 * so the JS layer always receives a concrete Given or Surname value.
 *
 * The backing values are the strings persisted in module preferences and posted
 * by the admin form, so `tryFrom()` is the boundary between stored configuration
 * and typed code: convert once on the way in, then pass the case around.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
enum NameAbbreviation: string
{
    /**
     * Use the tree's surname tradition to pick Given or Surname automatically.
     */
    case Auto = 'AUTO';

    /**
     * Abbreviate given names first (default for most traditions).
     */
    case Given = 'GIVEN';

    /**
     * Abbreviate surnames first (matches Icelandic patronymic usage).
     */
    case Surname = 'SURNAME';

    /**
     * The tree's SURNAME_TRADITION value for Icelandic naming. Its surnames are
     * patronymics and people are addressed by given name, so the surname is the
     * part to shorten — which is why it is the one tradition Auto treats
     * differently.
     */
    private const string ICELANDIC_TRADITION = 'icelandic';

    /**
     * Resolves this strategy against a tree's surname tradition. Auto maps to
     * Surname for Icelandic-tradition trees (where surnames are typically
     * patronymics and people are addressed by given name) and to Given for
     * everything else. A concrete case resolves to itself.
     *
     * @param string $surnameTradition The tree's SURNAME_TRADITION preference value
     *
     * @return self Always Given or Surname, never Auto
     */
    public function resolve(string $surnameTradition): self
    {
        if ($this !== self::Auto) {
            return $this;
        }

        return $surnameTradition === self::ICELANDIC_TRADITION
            ? self::Surname
            : self::Given;
    }
}
