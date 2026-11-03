<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Testing;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\AssertionFailedError;

use function libxml_clear_errors;
use function libxml_use_internal_errors;

/**
 * Queries the markup that a view of a module renders. A module uses it in the tests of its own
 * suite.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class MarkupFixture
{
    /**
     * Prevents instances of the fixture, because it only queries the markup it is given.
     */
    private function __construct()
    {
    }

    /**
     * Parses rendered markup into a document that can be queried.
     *
     * @param string $markup The rendered markup
     */
    public static function xpathOf(string $markup): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $markup);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    /**
     * Returns the elements that the expression finds.
     *
     * @param DOMXPath $xpath      The document to query
     * @param string   $expression The XPath expression
     *
     * @return list<DOMElement>
     */
    public static function elements(DOMXPath $xpath, string $expression): array
    {
        $elements = [];
        $nodes    = $xpath->query($expression);

        if ($nodes === false) {
            return $elements;
        }

        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    /**
     * Returns the first element that the expression finds and fails the test when there is none.
     *
     * @param DOMXPath $xpath      The document to query
     * @param string   $expression The XPath expression
     */
    public static function first(DOMXPath $xpath, string $expression): DOMElement
    {
        foreach (self::elements($xpath, $expression) as $element) {
            return $element;
        }

        throw new AssertionFailedError('The markup has no element for ' . $expression);
    }
}
