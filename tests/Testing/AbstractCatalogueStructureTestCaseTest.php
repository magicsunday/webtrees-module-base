<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Test\Testing;

use Closure;
use MagicSunday\Webtrees\ModuleBase\Test\Double\CatalogueStructureCaseDouble;
use MagicSunday\Webtrees\ModuleBase\Testing\AbstractCatalogueStructureTestCase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sort;
use function sprintf;

/**
 * Runs every check of the abstract catalogue structure test case against fixture
 * catalogues. A fixture with a defect isolates it, and the test pins which checks must
 * go red for it. A fixture without a defect pins that none of the checks goes red for
 * it. This includes inputs that only look suspicious, such as a path with glob
 * characters or Windows line endings. Some fixtures hold no catalogue at all. One is a
 * directory with only a placeholder file. Another names a directory that does not
 * exist, because git cannot store an empty one.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(AbstractCatalogueStructureTestCase::class)]
final class AbstractCatalogueStructureTestCaseTest extends TestCase
{
    /**
     * Resets the shared directory and the plural declaration of the double, so that no test
     * sees the state of a previous one, also when a check raised an unexpected exception.
     */
    protected function tearDown(): void
    {
        CatalogueStructureCaseDouble::$directory          = '';
        CatalogueStructureCaseDouble::$shipsPluralEntries = true;

        parent::tearDown();
    }

    /**
     * Provides every fixture with the checks that must fail for it, written as the name of
     * the check, followed by the locale for a check that takes one.
     *
     * @return array<string, array{string, list<string>}> The fixture rows, keyed by what the fixture shows, each with the fixture
     *                                                    directory and the expected failing checks
     */
    public static function fixtures(): array
    {
        return [
            'clean catalogues'                => ['clean', []],
            'plural entry with too few forms' => [
                'wrong-form-count',
                [
                    'pluralEntriesCarryExactlyTheFormsOfThePluralRule@cs',
                    'sourcePluralEntriesHaveTheSlotsOfThePluralRule@cs',
                ],
            ],
            'empty form behind a filled first form' => [
                'empty-middle-form',
                [
                    'noPluralFormIsEmpty@cs',
                    'everyPluralEntryRendersForEveryNumber@cs',
                    'pluralFormsKeepThePlaceholdersOfTheSource@cs',
                ],
            ],
            'empty first form' => [
                'empty-first-form',
                ['compiledCatalogueMatchesTheSourceCatalogue@cs'],
            ],
            'form that changes the placeholder' => [
                'wrong-placeholder',
                ['pluralFormsKeepThePlaceholdersOfTheSource@cs'],
            ],
            'fully empty entry with the wrong slot count' => [
                'empty-entry-wrong-slots',
                ['sourcePluralEntriesHaveTheSlotsOfThePluralRule@cs'],
            ],
            'header with the wrong form count' => [
                'header-wrong-count',
                ['headerDeclaresThePluralRuleOfWebtrees@cs'],
            ],
            'header rule split by other header lines' => [
                'header-split',
                ['headerDeclaresThePluralRuleOfWebtrees@cs'],
            ],
            'catalogue without a plural entry' => [
                'no-plural-entry',
                [
                    'sourcePluralEntriesHaveTheSlotsOfThePluralRule@cs',
                    'compiledCataloguesCarryPluralEntries',
                ],
            ],
            'fuzzy entry in the source' => [
                'fuzzy-entry',
                ['compiledCatalogueMatchesTheSourceCatalogue@cs'],
            ],
            'compiled catalogue older than its source' => [
                'stale-compiled',
                ['compiledCatalogueMatchesTheSourceCatalogue@cs'],
            ],
            'compiled catalogue without a source' => [
                'orphan-compiled',
                ['everyLocaleHasItsSourceAndItsCompiledCatalogue'],
            ],
            'directory without any locale' => [
                'no-locales',
                [
                    'everyLocaleHasItsSourceAndItsCompiledCatalogue',
                    'compiledCataloguesCarryPluralEntries',
                ],
            ],
            'plural entry the source reader cannot read' => [
                'unreadable-plural-entry',
                [
                    'sourcePluralEntriesHaveTheSlotsOfThePluralRule@cs',
                    'compiledCatalogueMatchesTheSourceCatalogue@cs',
                ],
            ],
            'catalogue directory with glob characters in its path' => ['glob-characters[1]', []],
            'plural entry with too many forms'                     => [
                'too-many-forms',
                [
                    'headerDeclaresThePluralRuleOfWebtrees@cs',
                    'pluralEntriesCarryExactlyTheFormsOfThePluralRule@cs',
                    'sourcePluralEntriesHaveTheSlotsOfThePluralRule@cs',
                ],
            ],
            'defects in plural entries that sort after a clean one' => [
                'defects-after-a-clean-entry',
                [
                    'pluralEntriesCarryExactlyTheFormsOfThePluralRule@cs',
                    'sourcePluralEntriesHaveTheSlotsOfThePluralRule@cs',
                    'noPluralFormIsEmpty@cs',
                    'everyPluralEntryRendersForEveryNumber@cs',
                    'pluralFormsKeepThePlaceholdersOfTheSource@cs',
                ],
            ],
            'integer placeholder dropped from a form' => [
                'dropped-integer-placeholder',
                ['pluralFormsKeepThePlaceholdersOfTheSource@cs'],
            ],
            'numbered placeholders in another order'   => ['reordered-numbered-placeholders', []],
            'unnumbered placeholders in another order' => [
                'reordered-unnumbered-placeholders',
                ['pluralFormsKeepThePlaceholdersOfTheSource@cs'],
            ],
            'wrong placeholder in the first form' => [
                'wrong-first-form-placeholder',
                ['pluralFormsKeepThePlaceholdersOfTheSource@cs'],
            ],
            'empty form that only the number one selects' => [
                'empty-form-selected-by-one',
                [
                    'noPluralFormIsEmpty@ar',
                    'everyPluralEntryRendersForEveryNumber@ar',
                    'pluralFormsKeepThePlaceholdersOfTheSource@ar',
                ],
            ],
            'source without a header' => [
                'no-header',
                ['headerDeclaresThePluralRuleOfWebtrees@cs'],
            ],
            'header with an empty plural formula' => [
                'header-empty-formula',
                ['headerDeclaresThePluralRuleOfWebtrees@cs'],
            ],
            'plural entries only in the last locale' => [
                'plural-only-in-last-locale',
                ['sourcePluralEntriesHaveTheSlotsOfThePluralRule@cs'],
            ],
            'numeric message id in the catalogue'                     => ['numeric-msgid', []],
            'comment block before the header and a quoted name in it' => ['commented-header', []],
            'wrapped catalogue strings'                               => ['wrapped-po-strings', []],
            'sources without a final line break'                      => ['no-trailing-newline', []],
            'sources with Windows line endings'                       => ['crlf-line-endings', []],
            'literal percent sign in the source text'                 => ['literal-percent', []],
            'numbered placeholder dropped from a form'                => [
                'wrong-numbered-placeholder',
                ['pluralFormsKeepThePlaceholdersOfTheSource@cs'],
            ],
            'singular source text without a placeholder'   => ['singular-without-placeholder', []],
            'empty form that only the number zero selects' => [
                'empty-form-selected-by-zero',
                [
                    'noPluralFormIsEmpty@lv',
                    'everyPluralEntryRendersForEveryNumber@lv',
                    'pluralFormsKeepThePlaceholdersOfTheSource@lv',
                ],
            ],
            'empty form that only numbers from a hundred select' => [
                'empty-form-selected-by-hundred',
                [
                    'noPluralFormIsEmpty@ar',
                    'everyPluralEntryRendersForEveryNumber@ar',
                    'pluralFormsKeepThePlaceholdersOfTheSource@ar',
                ],
            ],
            'compiled catalogue of a locale without a source next to a complete one' => [
                'orphan-next-to-complete',
                ['everyLocaleHasItsSourceAndItsCompiledCatalogue'],
            ],
            'plural entries only in the first locale' => [
                'plural-only-in-first-locale',
                ['sourcePluralEntriesHaveTheSlotsOfThePluralRule@zh-Hans'],
            ],
            'catalogue directory that does not exist' => [
                'does-not-exist',
                [
                    'everyLocaleHasItsSourceAndItsCompiledCatalogue',
                    'compiledCataloguesCarryPluralEntries',
                ],
            ],
            'source without a compiled catalogue' => [
                'missing-compiled',
                [
                    'everyLocaleHasItsSourceAndItsCompiledCatalogue',
                    'pluralEntriesCarryExactlyTheFormsOfThePluralRule@cs',
                    'noPluralFormIsEmpty@cs',
                    'compiledCatalogueMatchesTheSourceCatalogue@cs',
                    'everyPluralEntryRendersForEveryNumber@cs',
                    'pluralFormsKeepThePlaceholdersOfTheSource@cs',
                    'compiledCataloguesCarryPluralEntries',
                ],
            ],
        ];
    }

    /**
     * Runs every check against one fixture and compares the set of checks that fail with
     * the expected one, so a fixture pins exactly the checks its defect turns red.
     *
     * @param string       $fixture          The name of the fixture directory
     * @param list<string> $expectedFailures The checks that must fail, each named after the check
     *                                       and, for a check that takes a locale, followed by an
     *                                       at sign and that locale
     */
    #[Test]
    #[DataProvider('fixtures')]
    public function failsExactlyTheChecksThatMatchTheDefect(
        string $fixture,
        array $expectedFailures,
    ): void {
        $failures = $this->failingChecks($fixture, true);

        sort($expectedFailures);

        self::assertSame($expectedFailures, $failures);
    }

    /**
     * Lists the fixtures a subclass runs against when it declares that its catalogues ship
     * no plural entries, with the checks that must fail for each of them.
     *
     * @return array<string, array{string, list<string>}> The fixture name and the expected failures
     */
    public static function fixturesWithoutDeclaredPluralEntries(): array
    {
        return [
            'catalogues without plural entries pass' => ['no-plural-entry', []],
            'a plural entry fails the declaration'   => [
                'clean',
                [
                    'compiledCataloguesCarryPluralEntries',
                    'sourcePluralEntriesHaveTheSlotsOfThePluralRule@cs',
                    'sourcePluralEntriesHaveTheSlotsOfThePluralRule@zh-Hans',
                ],
            ],
        ];
    }

    /**
     * A subclass that declares no plural entries is checked for the absence instead of the
     * presence of plural entries, so one that is added later fails until the declaration
     * is removed.
     *
     * @param string       $fixture          The name of the fixture directory
     * @param list<string> $expectedFailures The checks that must fail, named as in the other matrix test
     */
    #[Test]
    #[DataProvider('fixturesWithoutDeclaredPluralEntries')]
    public function declaredAbsenceOfPluralEntriesIsCheckedBothWays(
        string $fixture,
        array $expectedFailures,
    ): void {
        $failures = $this->failingChecks($fixture, false);

        sort($expectedFailures);

        self::assertSame($expectedFailures, $failures);
    }

    /**
     * Runs every check against one fixture and returns the sorted names of the checks that
     * fail.
     *
     * @param string $fixture            The name of the fixture directory
     * @param bool   $shipsPluralEntries Whether the subclass declares that it ships plural entries
     *
     * @return list<string> The failing checks, each named after the check and, for a check that
     *                      takes a locale, followed by an at sign and that locale
     */
    private function failingChecks(string $fixture, bool $shipsPluralEntries): array
    {
        CatalogueStructureCaseDouble::$directory          = __DIR__ . '/../fixtures/catalogues/' . $fixture;
        CatalogueStructureCaseDouble::$shipsPluralEntries = $shipsPluralEntries;

        $case     = new CatalogueStructureCaseDouble('failingChecks');
        $failures = [];

        foreach ($this->globalChecks() as $name => $check) {
            if ($this->fails(static fn () => $check($case))) {
                $failures[] = $name;
            }
        }

        foreach (CatalogueStructureCaseDouble::shippedLocales() as [$locale]) {
            foreach ($this->localeChecks() as $name => $check) {
                if ($this->fails(static fn () => $check($case, $locale))) {
                    $failures[] = sprintf('%s@%s', $name, $locale);
                }
            }
        }

        sort($failures);

        return $failures;
    }

    /**
     * The data provider hands every locale that has a source catalogue to the checks, keyed
     * by the locale so that a failure names it.
     */
    #[Test]
    public function providesEveryShippedLocaleKeyedByItsName(): void
    {
        CatalogueStructureCaseDouble::$directory = __DIR__ . '/../fixtures/catalogues/clean';

        self::assertSame(
            [
                'cs'      => ['cs'],
                'zh-Hans' => ['zh-Hans'],
            ],
            CatalogueStructureCaseDouble::shippedLocales(),
        );
    }

    /**
     * Lists the checks that inspect the catalogue of one locale, by name.
     *
     * @return array<string, Closure(CatalogueStructureCaseDouble, string): void> The per-locale checks keyed by method name
     */
    private function localeChecks(): array
    {
        return [
            'headerDeclaresThePluralRuleOfWebtrees' => static fn (
                CatalogueStructureCaseDouble $case,
                string $locale,
            ) => $case->headerDeclaresThePluralRuleOfWebtrees($locale),
            'pluralEntriesCarryExactlyTheFormsOfThePluralRule' => static fn (
                CatalogueStructureCaseDouble $case,
                string $locale,
            ) => $case->pluralEntriesCarryExactlyTheFormsOfThePluralRule($locale),
            'sourcePluralEntriesHaveTheSlotsOfThePluralRule' => static fn (
                CatalogueStructureCaseDouble $case,
                string $locale,
            ) => $case->sourcePluralEntriesHaveTheSlotsOfThePluralRule($locale),
            'noPluralFormIsEmpty' => static fn (
                CatalogueStructureCaseDouble $case,
                string $locale,
            ) => $case->noPluralFormIsEmpty($locale),
            'compiledCatalogueMatchesTheSourceCatalogue' => static fn (
                CatalogueStructureCaseDouble $case,
                string $locale,
            ) => $case->compiledCatalogueMatchesTheSourceCatalogue($locale),
            'everyPluralEntryRendersForEveryNumber' => static fn (
                CatalogueStructureCaseDouble $case,
                string $locale,
            ) => $case->everyPluralEntryRendersForEveryNumber($locale),
            'pluralFormsKeepThePlaceholdersOfTheSource' => static fn (
                CatalogueStructureCaseDouble $case,
                string $locale,
            ) => $case->pluralFormsKeepThePlaceholdersOfTheSource($locale),
        ];
    }

    /**
     * Lists the checks that inspect the catalogues of all locales at once, by name.
     *
     * @return array<string, Closure(CatalogueStructureCaseDouble): void> The all-locale checks keyed by method name
     */
    private function globalChecks(): array
    {
        return [
            'everyLocaleHasItsSourceAndItsCompiledCatalogue' => static fn (CatalogueStructureCaseDouble $case) => $case->everyLocaleHasItsSourceAndItsCompiledCatalogue(),
            'compiledCataloguesCarryPluralEntries'           => static fn (CatalogueStructureCaseDouble $case) => $case->compiledCataloguesCarryPluralEntries(),
        ];
    }

    /**
     * Runs one check and tells whether it reported a failed assertion.
     *
     * @param Closure(): void $check The check to run, which may raise a failed assertion
     */
    private function fails(Closure $check): bool
    {
        try {
            $check();
        } catch (AssertionFailedError) {
            return true;
        }

        return false;
    }
}
