<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Double;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Tree;

/**
 * Builds the individual stub that the chart URL tests need: an individual that names itself by
 * its xref and by the name of its tree. A test case composes it, because the stubs come from the
 * test case's own factory.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
trait CreatesIndividualStubs
{
    /**
     * Builds an individual whose xref and tree name land in the URL.
     *
     * @param string $xref     The xref of the individual
     * @param string $treeName The name of the tree the individual belongs to
     *
     * @return Individual
     */
    private function createIndividual(string $xref, string $treeName): Individual
    {
        $tree = self::createStub(Tree::class);
        $tree->method('name')->willReturn($treeName);

        $individual = self::createStub(Individual::class);
        $individual->method('xref')->willReturn($xref);
        $individual->method('tree')->willReturn($tree);

        return $individual;
    }
}
