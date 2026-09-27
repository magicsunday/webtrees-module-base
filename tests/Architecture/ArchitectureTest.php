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
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Architecture rules executed by phpat through PHPStan. Each `#[TestRule]`
 * method returns one rule that pins a structural invariant so the codebase
 * cannot silently drift past the shape the production code relies on.
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
 * Deptrac first, phpat only where Deptrac cannot (magicsunday/coding-standard's
 * opt-in phpstan/phpat.neon). The whole layering above is enforced by Deptrac
 * (deptrac.yaml): the leaves and Facade are narrowed through overlay layers
 * (Deptrac checks a class against every layer it belongs to, so an overlay with
 * a shorter allow-list narrows a shared layer even though rulesets are united
 * across imports), and unlike phpat, Deptrac also checks the outgoing
 * dependencies of the trait-only Facade and Traits layers. What stays here is
 * what Deptrac cannot express: the four final-class rules, because a class
 * modifier is a structural invariant and Deptrac has no notion of one.
 *
 * A scope limit worth stating: phpat can only make a class-like the SUBJECT of
 * a rule when PHPStan reports it as a standalone declaration — a class,
 * interface or enum. It never analyses a trait on its own, so a rule keyed on
 * the Facade or Traits layer as its subject would match nothing and silently
 * pass; `check-phpat-subjects.php` (`composer ci:test:php:phpat-subjects`)
 * fails on such a vacuous subject.
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
     * `Model` value objects are final; the enums are implicitly final and are
     * excluded from the check.
     *
     * Why phpat: a structural invariant (a class modifier) Deptrac cannot inspect.
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
     * Why phpat: a structural invariant (a class modifier) Deptrac cannot inspect.
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
     * Why phpat: a structural invariant (a class modifier) Deptrac cannot inspect.
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
     * Why phpat: a structural invariant (a class modifier) Deptrac cannot inspect.
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
