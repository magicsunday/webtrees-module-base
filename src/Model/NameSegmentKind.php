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
 * The role a {@see NameSegment} plays inside a person's name. The backing value
 * is what a consumer reads from the serialised segment.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
enum NameSegmentKind: string
{
    /**
     * A given name, including the particles that webtrees renders in the given-name area.
     */
    case Given = 'given';

    /**
     * A surname part, including a surname suffix.
     */
    case Surname = 'surname';

    /**
     * The quoted nickname of the person.
     */
    case Nickname = 'nickname';

    /**
     * The bracketed married surname, shown next to the birth name.
     */
    case Married = 'married';
}
