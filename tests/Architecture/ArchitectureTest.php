<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Architecture;

use PHPat\Selector\Selector;
use PHPat\Selector\SelectorInterface;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Architecture rules executed by phpat through PHPStan. Each `#[TestRule]`
 * method returns one rule that pins a structural invariant so the codebase
 * cannot silently drift past the layering the production code relies on.
 *
 * Layering in this library (an arrow means "may depend on"):
 *
 *   - Traits    → Module          (module-level helpers for consuming modules)
 *   - Facade    → Contract        (data-facade traits, module/route injection)
 *   - Processor → Contract, Model, Support
 *   - Contract  (marker interfaces)                         — leaf
 *   - Module    (VersionInformation)                        — leaf
 *   - Model     (value objects + enums)                     — leaf
 *   - Support   (locale-independent / locale-aware helpers) — leaf
 *
 * The four leaf layers depend on no other `src/` layer; Processor composes the
 * leaves; Facade and Traits are the thin composition layer on top.
 *
 * A scope limit worth stating: phpat can only make a class-like the SUBJECT of
 * a rule when PHPStan reports it as a standalone declaration — a class,
 * interface or enum. It never analyses a trait on its own (a trait is checked
 * only inside the class that uses it), so a rule keyed on the Facade or Traits
 * layer as its subject would match nothing and silently pass. Both of those
 * layers are traits, so their outgoing dependencies are NOT pinned here; the
 * leaf rules still forbid every other layer from depending ON them. Verified by
 * breaking one rule of each declared kind (class, interface, enum) and watching
 * phpat report it — a trait-subject rule stayed green under the same violation.
 *
 * This class is not a PHPUnit test (it is excluded from the test suite in
 * phpunit.xml) — `#[CoversNothing]` only keeps it honest under
 * `requireCoverageMetadata`.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversNothing]
final class ArchitectureTest
{
    /**
     * The library's root namespace, used to build the per-layer selectors.
     */
    private const string NAMESPACE_ROOT = 'MagicSunday\\Webtrees\\ModuleBase';

    /**
     * Selects everything under the library root EXCEPT the layers a rule
     * permits, so a "X may depend only on Y" invariant is expressed as "X must
     * not depend on anything else under the root". Deriving the forbidden set
     * from the whole root rather than a hand-maintained layer list means a new
     * top-level namespace is forbidden by default — it cannot slip past a leaf
     * rule until someone remembers to register it.
     *
     * @param string ...$allowed The layers the subject may depend on (its own
     *                           layer is always implicitly allowed)
     *
     * @return SelectorInterface
     */
    private function everythingUnderRootExcept(string ...$allowed): SelectorInterface
    {
        $selectors = [Selector::inNamespace(self::NAMESPACE_ROOT)];

        // The test namespace lives under the root too but is never a production
        // dependency; exclude it so the rules speak only about `src/`.
        foreach (['Test', ...$allowed] as $layer) {
            $selectors[] = Selector::Not(Selector::inNamespace(self::NAMESPACE_ROOT . '\\' . $layer));
        }

        return Selector::AllOf(...$selectors);
    }

    /**
     * `Model` is a leaf: value objects and enums depend on no other `src/`
     * layer, so a formatter or processor can never leak back into the data shape.
     *
     * @return Rule
     */
    #[TestRule]
    public function modelIsALeaf(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Model'))
            ->shouldNot()
            ->dependOn()
            ->classes($this->everythingUnderRootExcept('Model'));
    }

    /**
     * `Support` is a leaf: the locale helpers depend on no other `src/` layer,
     * so they stay reusable without dragging a processor or facade along.
     *
     * @return Rule
     */
    #[TestRule]
    public function supportIsALeaf(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Support'))
            ->shouldNot()
            ->dependOn()
            ->classes($this->everythingUnderRootExcept('Support'));
    }

    /**
     * `Contract` holds marker interfaces only; they depend on no other `src/`
     * layer so any layer can implement them without a cycle.
     *
     * @return Rule
     */
    #[TestRule]
    public function contractIsALeaf(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Contract'))
            ->shouldNot()
            ->dependOn()
            ->classes($this->everythingUnderRootExcept('Contract'));
    }

    /**
     * `Module` holds the version-check helper; it depends on no other `src/`
     * layer.
     *
     * @return Rule
     */
    #[TestRule]
    public function moduleIsALeaf(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Module'))
            ->shouldNot()
            ->dependOn()
            ->classes($this->everythingUnderRootExcept('Module'));
    }

    /**
     * Processors compose the leaf layers (Contract, Model, Support) but never
     * the composition on top of them: not the facade traits, the module traits,
     * or the module helper.
     *
     * @return Rule
     */
    #[TestRule]
    public function processorDependsOnlyOnLeaves(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Processor'))
            ->shouldNot()
            ->dependOn()
            ->classes($this->everythingUnderRootExcept('Processor', 'Contract', 'Model', 'Support'));
    }

    /**
     * `Model` value objects are final; the enums are implicitly final and are
     * excluded from the check.
     *
     * @return Rule
     */
    #[TestRule]
    public function modelClassesAreFinal(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Model'))
            ->excluding(Selector::isEnum())
            ->should()
            ->beFinal();
    }

    /**
     * `Support` helpers are final.
     *
     * @return Rule
     */
    #[TestRule]
    public function supportClassesAreFinal(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Support'))
            ->should()
            ->beFinal();
    }

    /**
     * Processors are final: no consumer subclasses them, and the compact and
     * legacy APIs are meant to be used, not overridden.
     *
     * @return Rule
     */
    #[TestRule]
    public function processorClassesAreFinal(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Processor'))
            ->should()
            ->beFinal();
    }

    /**
     * The module-level helper is final.
     *
     * @return Rule
     */
    #[TestRule]
    public function moduleClassesAreFinal(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ROOT . '\\Module'))
            ->should()
            ->beFinal();
    }
}
