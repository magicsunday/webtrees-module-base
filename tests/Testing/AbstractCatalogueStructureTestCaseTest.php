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
 * catalogues. Each fixture carries exactly one defect, and the test pins which checks must
 * go red for it. The clean fixture pins that none of them goes red without a defect.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
#[CoversClass(AbstractCatalogueStructureTestCase::class)]
final class AbstractCatalogueStructureTestCaseTest extends TestCase
{
    protected function tearDown(): void
    {
        CatalogueStructureCaseDouble::$directory = '';

        parent::tearDown();
    }

    /**
     * Provides every fixture with the checks that must fail for it, written as the name of
     * the check, followed by the locale for a check that takes one.
     *
     * @return array<string, array{string, list<string>}>
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
     * @param list<string> $expectedFailures
     */
    #[Test]
    #[DataProvider('fixtures')]
    public function failsExactlyTheChecksThatMatchTheDefect(string $fixture, array $expectedFailures): void
    {
        CatalogueStructureCaseDouble::$directory = __DIR__ . '/../fixtures/catalogues/' . $fixture;

        $case     = new CatalogueStructureCaseDouble('failsExactlyTheChecksThatMatchTheDefect');
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
        sort($expectedFailures);

        self::assertSame($expectedFailures, $failures);
    }

    /**
     * Lists the checks that inspect the catalogue of one locale, by name.
     *
     * @return array<string, Closure(CatalogueStructureCaseDouble, string): void>
     */
    private function localeChecks(): array
    {
        return [
            'headerDeclaresThePluralRuleOfWebtrees'            => static fn (CatalogueStructureCaseDouble $case, string $locale) => $case->headerDeclaresThePluralRuleOfWebtrees($locale),
            'pluralEntriesCarryExactlyTheFormsOfThePluralRule' => static fn (CatalogueStructureCaseDouble $case, string $locale) => $case->pluralEntriesCarryExactlyTheFormsOfThePluralRule($locale),
            'sourcePluralEntriesHaveTheSlotsOfThePluralRule'   => static fn (CatalogueStructureCaseDouble $case, string $locale) => $case->sourcePluralEntriesHaveTheSlotsOfThePluralRule($locale),
            'noPluralFormIsEmpty'                              => static fn (CatalogueStructureCaseDouble $case, string $locale) => $case->noPluralFormIsEmpty($locale),
            'compiledCatalogueMatchesTheSourceCatalogue'       => static fn (CatalogueStructureCaseDouble $case, string $locale) => $case->compiledCatalogueMatchesTheSourceCatalogue($locale),
            'everyPluralEntryRendersForEveryNumber'            => static fn (CatalogueStructureCaseDouble $case, string $locale) => $case->everyPluralEntryRendersForEveryNumber($locale),
            'pluralFormsKeepThePlaceholdersOfTheSource'        => static fn (CatalogueStructureCaseDouble $case, string $locale) => $case->pluralFormsKeepThePlaceholdersOfTheSource($locale),
        ];
    }

    /**
     * Lists the checks that inspect the catalogues of all locales at once, by name.
     *
     * @return array<string, Closure(CatalogueStructureCaseDouble): void>
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
     * @param Closure(): void $check
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
