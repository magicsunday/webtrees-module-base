<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Processor;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Fisharebest\Webtrees\Individual;

/**
 * Class NameProcessor.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
class NameProcessor
{
    /**
     * The full name identifier with name placeholders.
     */
    private const string FULL_NAME_WITH_PLACEHOLDERS = 'fullNN';

    /**
     * The full name identifier.
     */
    private const string FULL_NAME = 'full';

    /**
     * The XPath identifier to extract the first name parts (including the
     * prefix).
     */
    private const string XPATH_FIRST_NAMES
        = '//text()[not(ancestor::q[@class="wt-nickname"]) and not(preceding::span[@class="SURN"] or ancestor::span[@class="SURN"])]';

    /**
     * The XPath identifier to extract the last name parts (surname + surname
     * suffix).
     */
    private const string XPATH_LAST_NAMES = '//span[@class="NAME"]//span[@class="SURN"]/text()|//span[@class="SURN"]/following::text()';

    /**
     * The XPath identifier to extract the starred name part.
     */
    private const string XPATH_PREFERRED_NAME = '//span[@class="NAME"]//span[@class="starredname"]/text()';

    /**
     * The individual's primary name array.
     *
     * @var string[]
     */
    private array $primaryName;

    /**
     * The DOM xpath processor.
     */
    private readonly DOMXPath $xPath;

    /**
     * Constructor.
     *
     * @param Individual      $individual     The individual to process
     * @param Individual|null $spouse
     * @param bool            $useMarriedName TRUE to return the married name instead of the primary one
     */
    public function __construct(
        /**
         * The individual.
         */
        private readonly Individual $individual,
        ?Individual $spouse = null,
        bool $useMarriedName = false,
    ) {
        $this->primaryName = $this->extractPrimaryName($spouse, $useMarriedName);

        // The formatted name of the individual (containing HTML) is the input to the xpath processor
        $this->xPath = $this->getDomXPathInstance($this->primaryName[self::FULL_NAME]);
    }

    /**
     * Returns the DOMXPath instance.
     *
     * @param string $input The input used as xpath base
     *
     * @return DOMXPath
     */
    private function getDomXPathInstance(string $input): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadHTML($this->convertToHtmlEntities($input));

        return new DOMXPath($document);
    }

    /**
     * Extracts the primary name from the individual.
     *
     * @param Individual|null $spouse
     * @param bool            $useMarriedName TRUE to return the married name instead of the primary one
     *
     * @return array<string, string>
     */
    private function extractPrimaryName(
        ?Individual $spouse = null,
        bool $useMarriedName = false,
    ): array {
        $individualNames = $this->individual->getAllNames();

        if ($useMarriedName) {
            foreach ($individualNames as $individualName) {
                if ($spouse instanceof Individual) {
                    foreach ($spouse->getAllNames() as $spouseName) {
                        if ($individualName['type'] !== '_MARNM') {
                            continue;
                        }

                        if ($individualName['surn'] !== $spouseName['surn']) {
                            continue;
                        }

                        return $individualName;
                    }
                } elseif ($individualName['type'] === '_MARNM') {
                    return $individualName;
                }
            }
        }

        return $individualNames[$this->individual->getPrimaryName()];
    }

    /**
     * Returns the UTF-8 chars converted to HTML entities.
     *
     * @param string $input The input to encode
     *
     * @return string
     */
    private function convertToHtmlEntities(string $input): string
    {
        return mb_encode_numericentity($input, [0x80, 0xFFFFFFF, 0, 0xFFFFFFF], 'UTF-8');
    }

    /**
     * Replace name placeholders.
     *
     * @param string $value
     *
     * @return string
     */
    private function replacePlaceholders(string $value): string
    {
        return trim(
            str_replace(
                [
                    Individual::NOMEN_NESCIO,
                    Individual::PRAENOMEN_NESCIO,
                ],
                '…',
                $value
            )
        );
    }

    /**
     * Returns the full name of the individual without formatting of the
     * individual parts of the name. All placeholders were removed as we do not
     * need them in this module.
     *
     * @return string
     */
    public function getFullName(): string
    {
        // The name of the person without formatting of the individual parts of the name.
        // Remove placeholders as we do not need them in this module
        return $this->replacePlaceholders($this->primaryName[self::FULL_NAME_WITH_PLACEHOLDERS]);
    }

    /**
     * Returns the GEDCOM `2 NICK` value of the individual's *primary* NAME
     * fact, or an empty string when no nickname is set there. NAME facts whose
     * `2 TYPE` is something other than the primary identity (e.g. `_MARNM`,
     * `aka`) are skipped: a nickname attached to the married name belongs to
     * the married identity, not the birth identity that `getFullName()` returns
     * by default.
     *
     * @return string
     */
    public function getNickname(): string
    {
        foreach ($this->individual->facts(['NAME']) as $nameFact) {
            $type = $nameFact->attribute('TYPE');

            // Skip non-primary NAME variants (married name, also-known-as, etc.)
            // so the nickname injection sticks to the birth-identity name.
            if ($type !== '' && strtoupper($type) !== 'BIRTH') {
                continue;
            }

            $nick = $nameFact->attribute('NICK');

            if ($nick !== '') {
                return $nick;
            }
        }

        return '';
    }

    /**
     * Returns the full name with the nickname injected in quotes between the
     * given names and the surname (e.g. `John "Jonny" Doe`). When the GEDCOM
     * has no NICK, or when the displayed name already contains the nickname
     * inline, the unmodified full name is returned.
     *
     * Mirrors the legacy webtrees ≤ 1.x behaviour and the
     * `BertKoor/wt-module-old-nicknames` data-fix output, but operates at
     * display time without modifying the GEDCOM.
     *
     * @return string
     */
    public function getFullNameWithNickname(): string
    {
        $nick = $this->getNickname();

        if ($nick === '') {
            return $this->getFullName();
        }

        return $this->injectNickname(
            $this->getFullName(),
            $this->getFirstNames(),
            $this->getLastNames(),
            $nick
        );
    }

    /**
     * Inserts the quoted nickname after the last given name in a flat name
     * string, which lands it before whatever comes next (a surname particle
     * like `von` followed by the surname, the surname itself, a married-name
     * suffix, etc.). Idempotent: if the nickname is already present in quotes,
     * the input is returned unchanged.
     *
     * Anchoring on the last given name (rather than the first surname token)
     * keeps particles like `von`, `de la`, `van der` -- which webtrees renders
     * inside the given-name area when they sit outside `/SURN/` slashes --
     * attached to the surname they belong to instead of letting the nickname
     * split them off.
     *
     * The strrpos search is constrained to the given-name region of the string
     * (everything before the first surname token). Without that bound, a last
     * given name that happens to be a substring of the surname would anchor the
     * insertion inside the surname — for "Hendrik Jan /Jansen/" with last given
     * name "Jan", strrpos would otherwise hit "Jan" inside "Jansen".
     *
     * @param string   $fullName   Plain full name (e.g. "John Doe")
     * @param string[] $firstNames Given-name tokens as returned by getFirstNames()
     * @param string[] $lastNames  Surname tokens as returned by getLastNames()
     * @param string   $nick       Nickname without quotes (e.g. "Jonny")
     *
     * @return string
     */
    private function injectNickname(
        string $fullName,
        array $firstNames,
        array $lastNames,
        string $nick,
    ): string {
        if (($nick === '') || str_contains($fullName, '"' . $nick . '"')) {
            return $fullName;
        }

        $lastGivenName = end($firstNames);

        if (($lastGivenName === false) || ($lastGivenName === '')) {
            return $fullName . ' "' . $nick . '"';
        }

        $searchHaystack = $fullName;
        $firstSurname   = $lastNames[0] ?? '';

        if ($firstSurname !== '') {
            $surnamePos = strpos($fullName, $firstSurname);

            if ($surnamePos !== false) {
                $searchHaystack = substr($fullName, 0, $surnamePos);
            }
        }

        $position = strrpos($searchHaystack, $lastGivenName);

        if ($position === false) {
            return $fullName . ' "' . $nick . '"';
        }

        $insertAt = $position + strlen($lastGivenName);

        return substr_replace($fullName, ' "' . $nick . '"', $insertAt, 0);
    }

    /**
     * Splits a name into an array, removing all name placeholders.
     *
     * @param string[] $names
     *
     * @return string[]
     */
    private function splitAndCleanName(array $names): array
    {
        $values = [[]];

        foreach ($names as $name) {
            $values[] = explode(' ', $name);
        }

        // Remove empty values and reindex array
        return array_values(
            array_filter(
                array_merge(...$values),
                static fn (string $value): bool => $value !== ''
            )
        );
    }

    /**
     * Returns all name parts by given identifier.
     *
     * @param string $expression The XPath expression to execute
     *
     * @return string[]
     */
    private function getNamesByIdentifier(string $expression): array
    {
        $nodeList = $this->xPath->query($expression);
        $names    = [];

        if ($nodeList !== false) {
            /** @var DOMNode $node */
            foreach ($nodeList as $node) {
                $names[] = $node->nodeValue ?? '';
            }
        }

        // Remove all leading/trailing whitespace characters
        $names = array_map(trim(...), $names);

        return $this->splitAndCleanName($names);
    }

    /**
     * Returns all assigned first names of the individual.
     *
     * @return string[]
     */
    public function getFirstNames(): array
    {
        return $this->getNamesByIdentifier(self::XPATH_FIRST_NAMES);
    }

    /**
     * Returns all assigned last names of the individual.
     *
     * @return string[]
     */
    public function getLastNames(): array
    {
        return $this->getNamesByIdentifier(self::XPATH_LAST_NAMES);
    }

    /**
     * Returns the married surname parts (from a `_MARNM` GEDCOM record), or an
     * empty array when no married-name record matches. When $spouse is given,
     * only `_MARNM` records whose `surn` matches the spouse's `surn` are
     * considered; otherwise any `_MARNM` is returned.
     *
     * Use this in a chart consumer that already shows the birth name and wants
     * to append the married surname (e.g. "Schmidt (Müller)") — separate from
     * the `useMarriedName` constructor flag, which switches the primary name
     * out entirely.
     *
     * @param Individual|null $spouse Optional spouse to scope the surname match
     *
     * @return string[]
     */
    public function getMarriedSurnames(?Individual $spouse = null): array
    {
        foreach ($this->individual->getAllNames() as $individualName) {
            if ($individualName['type'] !== '_MARNM') {
                continue;
            }

            if ($spouse instanceof Individual) {
                $spouseHasMatch = false;

                foreach ($spouse->getAllNames() as $spouseName) {
                    if ($individualName['surn'] === $spouseName['surn']) {
                        $spouseHasMatch = true;

                        break;
                    }
                }

                if (!$spouseHasMatch) {
                    continue;
                }
            }

            return $this->splitAndCleanName([$individualName['surn']]);
        }

        return [];
    }

    /**
     * Returns the preferred name of the individual.
     *
     * @return string
     */
    public function getPreferredName(): string
    {
        $nodeList = $this->xPath->query(self::XPATH_PREFERRED_NAME);

        if (($nodeList !== false) && ($nodeList->length > 0)) {
            $nodeItem = $nodeList->item(0);

            return ($nodeItem !== null) ? ($nodeItem->nodeValue ?? '') : '';
        }

        return '';
    }

    /**
     * Returns the alternative name of the individual.
     *
     * @param Individual $individual
     *
     * @return string
     */
    public function getAlternateName(Individual $individual): string
    {
        if (
            $individual->canShowName()
            && ($individual->getPrimaryName() !== $individual->getSecondaryName())
        ) {
            $allNames        = $individual->getAllNames();
            $alternativeName = $allNames[$individual->getSecondaryName()][self::FULL_NAME_WITH_PLACEHOLDERS];

            return $this->replacePlaceholders($alternativeName);
        }

        return '';
    }
}
