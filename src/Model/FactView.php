<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Model;

use JsonSerializable;

/**
 * One fact of a person as a chart box shows it: the event label, its date, its place and
 * its value, and for an event of a family the name of the other spouse. The label and the
 * date are reduced to plain text. The place is formatted by the configured place style, the
 * full place is the place name of webtrees and the value is the canonical value of the fact,
 * none of them reduced to plain text. A fact whose value is a pointer to another record, such
 * as a shared note, has the name of that record as its value, reduced to plain text, and an
 * empty value when the record is not visible. A character such as "<" or "&" may appear in any
 * of them, so a consumer that writes them into an HTML context has to escape them there.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class FactView implements JsonSerializable
{
    /**
     * @param string $tag       The GEDCOM tag of the fact that was found, which for a birth or death slot
     *                          can be an equivalent such as CHR or BURI
     * @param string $label     The localised label of the fact
     * @param string $date      The date text, in the compact format for a complete date and as the
     *                          webtrees display text for a partial, qualified or ranged one
     * @param string $place     The place formatted by the configured place style
     * @param string $placeFull The full GEDCOM place name, meant for a tooltip
     * @param string $value     The canonical value of the fact, or the name of the linked record for a
     *                          fact whose value is a pointer
     * @param string $partner   The name of the other spouse as webtrees shows it to the viewer, for an event
     *                          of a family such as a marriage. For a private spouse it is a placeholder when
     *                          the tree shows private relationships and empty when it does not, and it is
     *                          empty for an event of the person and for a family without a spouse
     * @param int    $sortDay   The Julian day number of the first day of the date as written, without regard to a
     *                          qualifier such as "after" or "before", for sorting facts by time, zero for a
     *                          fact without a usable date
     */
    public function __construct(
        public string $tag,
        public string $label,
        public string $date,
        public string $place,
        public string $placeFull,
        public string $value,
        public string $partner = '',
        public int $sortDay = 0,
    ) {
    }

    /**
     * Returns the fact as an array.
     *
     * @return array{tag: string, label: string, date: string, place: string, placeFull: string, value: string, partner: string, sortDay: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'tag'       => $this->tag,
            'label'     => $this->label,
            'date'      => $this->date,
            'place'     => $this->place,
            'placeFull' => $this->placeFull,
            'value'     => $this->value,
            'partner'   => $this->partner,
            'sortDay'   => $this->sortDay,
        ];
    }
}
