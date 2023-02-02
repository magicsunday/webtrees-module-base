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
    private const FULL_NAME_WITH_PLACEHOLDERS = 'fullNN';

    /**
     * The full name identifier.
     */
    private const FULL_NAME = 'full';

    /**
     * The XPath identifier to extract the first name parts.
     */
    private const XPATH_FIRST_NAMES
        = '//span[@class="NAME"]//text()[parent::*[not(@class="wt-nickname") and not(@class="SURN")]]';

    /**
     * The XPath identifier to extract the last name parts (surname + surname suffix).
     */
    private const XPATH_LAST_NAMES
        = '//span[@class="NAME"]//span[@class="SURN"]/text()|//span[@class="SURN"]/following::text()';

    /**
     * The XPath identifier to extract the nickname part.
     */
    private const XPATH_NICKNAME = '//span[@class="NAME"]//q[@class="wt-nickname"]/text()';

    /**
     * The XPath identifier to extract the starred name part.
     */
    private const XPATH_PREFERRED_NAME = '//span[@class="NAME"]//span[@class="starredname"]/text()';

    /**
     * The XPath identifier to extract the alternative name parts.
     */
    private const XPATH_ALTERNATIVE_NAME = '//span[contains(attribute::class, "NAME")]';

    /**
     * The individual.
     *
     * @var Individual
     */
    private Individual $individual;

    /**
     * The individuals primary name array.
     *
     * @var string[]
     */
    private array $primaryName;

    /**
     * The DOM xpath processor.
     *
     * @var DOMXPath
     */
    private $xPath;

    /**
     * Constructor.
     *
     * @param Individual $individual The individual to process
     */
    public function __construct(Individual $individual)
    {
        $this->individual  = $individual;
        $this->primaryName = $this->extractPrimaryName();

        // The formatted name of the individual (containing HTML) is the input to the xpath processor
        $this->xPath = $this->getDomXPathInstance($this->primaryName[self::FULL_NAME]);
    }

    /**
     * Extracts the primary name from the individual.
     *
     * @return string[]
     */
    private function extractPrimaryName(): array
    {
        return $this->individual->getAllNames()[$this->individual->getPrimaryName()];
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
        return mb_encode_numericentity($input, [0x80, 0xfffffff, 0, 0xfffffff], 'UTF-8');
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
     * Returns the full name of the individual without formatting of the individual parts of the name.
     * All placeholders were removed as we do not need them in this module.
     *
     * @return string
     */
    public function getFullName(): string
    {
        // The name of the person without formatting of the individual parts of the name.
        // Remove placeholders as we do not need them in this module
        return str_replace(
            [
                Individual::NOMEN_NESCIO,
                Individual::PRAENOMEN_NESCIO,
            ],
            '',
            $this->primaryName[self::FULL_NAME_WITH_PLACEHOLDERS]
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

        // Remove all leading/trailing whitespaces
        $names = array_map('trim', $names);

        // Remove empty values and reindex array
        return array_values(array_filter($names));
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
     * Returns all assigned nicknames of the individual.
     *
     * @return string[]
     */
    public function getNicknames(): array
    {
        return $this->getNamesByIdentifier(self::XPATH_NICKNAME);
    }

    /**
     * Returns the alternative names of the individual.
     *
     * @return string[]
     */
    public function getAlternateNames(): array
    {
        $name = $this->individual->alternateName();

        if ($name === null) {
            return [];
        }

        $xPath    = $this->getDomXPathInstance($name);
        $nodeList = $xPath->query(self::XPATH_ALTERNATIVE_NAME);

        if (($nodeList !== false) && ($nodeList->length > 0)) {
            $nodeItem = $nodeList->item(0);
            $name     = ($nodeItem !== null) ? ($nodeItem->nodeValue ?? '') : '';
        }

        return array_filter(explode(' ', $name));
    }
}
