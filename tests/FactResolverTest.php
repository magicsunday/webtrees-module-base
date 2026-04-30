<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test;

use Fisharebest\Webtrees\Tree;
use MagicSunday\Webtrees\ModuleBase\Processor\FactResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * FactResolverTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
class FactResolverTest extends TestCase
{
    /**
     * Builds a FactResolver backed by a Tree stub that returns the given
     * string for the CHART_BOX_TAGS preference.
     */
    private function resolverWithTags(string $chartBoxTags): FactResolver
    {
        $tree = self::createStub(Tree::class);
        $tree->method('getPreference')->willReturn($chartBoxTags);

        return new FactResolver($tree);
    }

    #[Test]
    public function optionalTagsReturnsEntriesFromCsvPreference(): void
    {
        $resolver = $this->resolverWithTags('OCCU,RESI,MARR');

        self::assertSame(['OCCU', 'RESI', 'MARR'], $resolver->optionalTags());
    }

    #[Test]
    public function optionalTagsHandlesWhitespaceAndMixedSeparators(): void
    {
        $resolver = $this->resolverWithTags(" OCCU , RESI\nMARR ");

        self::assertSame(['OCCU', 'RESI', 'MARR'], $resolver->optionalTags());
    }

    #[Test]
    public function optionalTagsStripsBirthAndDeathEquivalents(): void
    {
        // BIRT, CHR, BAPM, DEAT, BURI, CREM are always rendered in their
        // dedicated positions; they must not appear in the optional list.
        $resolver = $this->resolverWithTags('BIRT,CHR,BAPM,OCCU,DEAT,BURI,CREM,RESI');

        self::assertSame(['OCCU', 'RESI'], $resolver->optionalTags());
    }

    #[Test]
    public function optionalTagsHonoursCallerExcludes(): void
    {
        // Ancestor-only charts pass ['MARR'] so couples' marriages do not
        // bleed into an individual ancestor's box.
        $resolver = $this->resolverWithTags('OCCU,RESI,MARR');

        self::assertSame(['OCCU', 'RESI'], $resolver->optionalTags(['MARR']));
    }

    #[Test]
    public function effectiveTagsPutsBirthAndDeathFirstThenOptionalList(): void
    {
        $resolver = $this->resolverWithTags('OCCU,RESI');

        self::assertSame(
            [FactResolver::BIRTH_PLACEHOLDER, FactResolver::DEATH_PLACEHOLDER, 'OCCU', 'RESI'],
            $resolver->effectiveTags(true)
        );
    }

    #[Test]
    public function effectiveTagsOmitsOptionalListWhenShowAdditionalIsFalse(): void
    {
        $resolver = $this->resolverWithTags('OCCU,RESI,MARR');

        self::assertSame(
            [FactResolver::BIRTH_PLACEHOLDER, FactResolver::DEATH_PLACEHOLDER],
            $resolver->effectiveTags(false)
        );
    }

    #[Test]
    public function effectiveTagsAppliesExcludesToOptionalList(): void
    {
        $resolver = $this->resolverWithTags('OCCU,MARR,RESI');

        self::assertSame(
            [FactResolver::BIRTH_PLACEHOLDER, FactResolver::DEATH_PLACEHOLDER, 'OCCU', 'RESI'],
            $resolver->effectiveTags(true, ['MARR'])
        );
    }

    #[Test]
    public function effectiveTagsReturnsMinimalLayoutForEmptyPreference(): void
    {
        $resolver = $this->resolverWithTags('');

        self::assertSame(
            [FactResolver::BIRTH_PLACEHOLDER, FactResolver::DEATH_PLACEHOLDER],
            $resolver->effectiveTags(true)
        );
    }
}
