<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Processor;

use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use MagicSunday\Webtrees\ModuleBase\Model\Symbols;

/**
 * Extracts and formats birth, death, and marriage dates from an Individual.
 *
 * The legacy methods (getBirthDate, getDeathDate, getLifetimeDescription,
 * getMarriageDate, getMarriageDateOfParents) preserve the locale-aware
 * webtrees display() output for backwards compatibility.
 *
 * Generation-aware "compact" methods (added in 1.1.0 — getFormattedBirthDate,
 * getFormattedDeathDate, getBirthDateFull, getDeathDateFull, getMarriageDateFull,
 * getCompactLifetimeDescription, formatMarriageDate) format dates as DD.MM.YYYY
 * or year-only depending on the individual's generation depth, suitable for
 * arc text where space is constrained. They use the Symbols enum for
 * birth/death markers.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
class DateProcessor
{
    /**
     * The birthdate of the individual.
     */
    private readonly Date $birthDate;

    /**
     * The death date of the individual.
     */
    private readonly Date $deathDate;

    /**
     * @param Individual $individual              The individual to process
     * @param int        $generation              1-based generation depth of this individual in the tree (0 = unset)
     * @param int        $detailedDateGenerations Generations at or below this depth show DD.MM.YYYY in the
     *                                            generation-aware methods; defaults to PHP_INT_MAX so that
     *                                            calls without explicit values always behave as "detailed"
     */
    public function __construct(
        private readonly Individual $individual,
        private readonly int $generation = 0,
        private readonly int $detailedDateGenerations = PHP_INT_MAX,
    ) {
        $this->birthDate = $this->individual->getBirthDate();
        $this->deathDate = $this->individual->getDeathDate();
    }

    // -----------------------------------------------------------------------
    // Legacy API (preserved from 1.0.x — locale-aware webtrees display())
    // -----------------------------------------------------------------------

    /**
     * Returns the year of birth (0 when no parseable date is recorded).
     *
     * @return int
     */
    public function getBirthYear(): int
    {
        return $this->birthDate->minimumDate()->year();
    }

    /**
     * Returns the year of death (0 when no parseable date is recorded).
     *
     * @return int
     */
    public function getDeathYear(): int
    {
        return $this->deathDate->minimumDate()->year();
    }

    /**
     * Returns the formatted birth date without HTML tags using webtrees' locale-aware display.
     *
     * @return string
     */
    public function getBirthDate(): string
    {
        return $this->decodeValue($this->birthDate->display());
    }

    /**
     * Returns the formatted death date without HTML tags using webtrees' locale-aware display.
     *
     * @return string
     */
    public function getDeathDate(): string
    {
        return $this->decodeValue($this->deathDate->display());
    }

    /**
     * Returns a localised lifetime label ("1853-1933", "Born: 1853", "Died: 1933", "Deceased").
     *
     * @return string
     */
    public function getLifetimeDescription(): string
    {
        if ($this->birthDate->isOK() && $this->deathDate->isOK()) {
            return $this->getBirthYear() . '-' . $this->getDeathYear();
        }

        if ($this->birthDate->isOK()) {
            return I18N::translate('Born: %s', (string) $this->getBirthYear());
        }

        if ($this->deathDate->isOK()) {
            return I18N::translate('Died: %s', (string) $this->getDeathYear());
        }

        if ($this->individual->isDead()) {
            return I18N::translate('Deceased');
        }

        return '';
    }

    /**
     * Returns the marriage date of the individual using webtrees' locale-aware display.
     *
     * @return string
     */
    public function getMarriageDate(): string
    {
        /** @var Family|null $family */
        $family = $this->individual->spouseFamilies()->first();

        if ($family !== null) {
            return $this->decodeValue($family->getMarriageDate()->display());
        }

        return '';
    }

    /**
     * Returns the marriage date of the parents using webtrees' locale-aware display.
     *
     * @return string
     */
    public function getMarriageDateOfParents(): string
    {
        /** @var Family|null $family */
        $family = $this->individual->childFamilies()->first();

        if ($family !== null) {
            return $this->decodeValue($family->getMarriageDate()->display());
        }

        return '';
    }

    // -----------------------------------------------------------------------
    // Generation-aware compact API (added in 1.1.0)
    // -----------------------------------------------------------------------

    /**
     * Returns true when the individual has a parseable birth date.
     *
     * @return bool
     */
    public function hasBirthDate(): bool
    {
        return $this->birthDate->isOK();
    }

    /**
     * Returns true when the individual has a parseable death date.
     *
     * @return bool
     */
    public function hasDeathDate(): bool
    {
        return $this->deathDate->isOK();
    }

    /**
     * Returns true when webtrees considers the individual to be deceased,
     * even if no explicit death date is recorded.
     *
     * @return bool
     */
    public function isDead(): bool
    {
        return $this->individual->isDead();
    }

    /**
     * Returns the generation-appropriate birth date string (no symbol prefix).
     * Empty string when no valid birth date exists.
     *
     * @return string
     */
    public function getFormattedBirthDate(): string
    {
        return $this->birthDate->isOK() ? $this->getLifeEventDate($this->birthDate) : '';
    }

    /**
     * Returns the generation-appropriate death date string (no symbol prefix).
     * Empty string when no valid death date exists.
     *
     * @return string
     */
    public function getFormattedDeathDate(): string
    {
        return $this->deathDate->isOK() ? $this->getLifeEventDate($this->deathDate) : '';
    }

    /**
     * Returns the full compact birth date (DD.MM.YYYY), regardless of
     * the generation detail setting. Suitable for tooltip display.
     *
     * @return string
     */
    public function getBirthDateFull(): string
    {
        return $this->birthDate->isOK() ? $this->formatCompactDate($this->birthDate) : '';
    }

    /**
     * Returns the full compact death date (DD.MM.YYYY), regardless of
     * the generation detail setting. Suitable for tooltip display.
     *
     * @return string
     */
    public function getDeathDateFull(): string
    {
        return $this->deathDate->isOK() ? $this->formatCompactDate($this->deathDate) : '';
    }

    /**
     * Returns the full compact marriage date (DD.MM.YYYY), regardless of
     * the generation detail setting. Suitable for tooltip display.
     *
     * @return string
     */
    public function getMarriageDateFull(): string
    {
        $family = $this->individual->spouseFamilies()->first();

        if (($family !== null) && $family->getMarriageDate()->isOK()) {
            return $this->formatCompactDate($family->getMarriageDate());
        }

        return '';
    }

    /**
     * Returns a compact single-line lifetime description (e.g. "1853–1933").
     * Falls back to the birth or death symbol with a single year, or to the
     * lone death symbol for deceased individuals without dates.
     *
     * @return string
     */
    public function getCompactLifetimeDescription(): string
    {
        $birthYear = $this->birthDate->isOK() ? $this->getYear($this->birthDate) : 0;
        $deathYear = $this->deathDate->isOK() ? $this->getYear($this->deathDate) : 0;

        if (($birthYear > 0) && ($deathYear > 0)) {
            return $birthYear . Symbols::DateRangeSeparator->value . $deathYear;
        }

        if ($birthYear > 0) {
            return Symbols::Birth->value . ' ' . $birthYear;
        }

        if ($deathYear > 0) {
            return Symbols::Death->value . ' ' . $deathYear;
        }

        if ($this->individual->isDead()) {
            return Symbols::Death->value;
        }

        return '';
    }

    /**
     * Formats a marriage date for chart display based on generation depth and the
     * configured detail threshold. Static so callers can format marriage dates from
     * arbitrary family records without instantiating a DateProcessor.
     *
     * Marriage arcs sit one level deeper than the individual itself; the effective
     * depth therefore equals generation + 1. Returns empty when the effective depth
     * exceeds 8 (no space available). Uses DD.MM.YYYY up to generation 6, year-only
     * beyond.
     *
     * @param Date $date                    The marriage date to format
     * @param int  $generation              1-based generation depth of the individual
     * @param int  $detailedDateGenerations Generations at or below this depth show DD.MM.YYYY
     *
     * @return string
     */
    public static function formatMarriageDate(Date $date, int $generation, int $detailedDateGenerations): string
    {
        $effectiveDepth = $generation + 1;

        if ($effectiveDepth > 8) {
            return '';
        }

        $calendarDate = $date->minimumDate();

        if ($effectiveDepth <= min($detailedDateGenerations, 6)) {
            return $calendarDate->format('%d.%m.%Y');
        }

        return (string) $calendarDate->year();
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Removes HTML tags and converts/decodes HTML entities to their corresponding characters.
     *
     * @param string $value
     *
     * @return string
     */
    private function decodeValue(string $value): string
    {
        return html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Formats a Date as DD.MM.YYYY using its minimum date.
     *
     * @param Date $date
     *
     * @return string
     */
    private function formatCompactDate(Date $date): string
    {
        return $date->minimumDate()->format('%d.%m.%Y');
    }

    /**
     * Returns the date in full DD.MM.YYYY format for inner generations, or as a
     * bare year for outer generations beyond the detail threshold.
     *
     * @param Date $date
     *
     * @return string
     */
    private function getLifeEventDate(Date $date): string
    {
        if ($this->generation <= $this->detailedDateGenerations) {
            return $this->formatCompactDate($date);
        }

        return (string) $this->getYear($date);
    }

    /**
     * Extracts the calendar year from the minimum date of a Date range.
     *
     * @param Date $date
     *
     * @return int
     */
    private function getYear(Date $date): int
    {
        return $date->minimumDate()->year();
    }
}
