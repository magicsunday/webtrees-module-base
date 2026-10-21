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
 * One drawable part of a person's name. A list of segments follows the display
 * order, so a consumer only draws the segments and never has to locate name parts
 * inside the flat full name by string search.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final readonly class NameSegment implements JsonSerializable
{
    /**
     * @param string          $label       The text to draw, a name token or the whole nickname (a nickname
     *                                     carries its quotes, a married surname its brackets)
     * @param NameSegmentKind $kind        The role of the segment inside the name
     * @param bool            $isPreferred TRUE if webtrees marks the token as the preferred given name
     */
    public function __construct(
        public string $label,
        public NameSegmentKind $kind,
        public bool $isPreferred = false,
    ) {
    }

    /**
     * Returns the segment as an array.
     *
     * @return array{label: string, kind: string, isPreferred: bool}
     */
    public function jsonSerialize(): array
    {
        return [
            'label'       => $this->label,
            'kind'        => $this->kind->value,
            'isPreferred' => $this->isPreferred,
        ];
    }
}
