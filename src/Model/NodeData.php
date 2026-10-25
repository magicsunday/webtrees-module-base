<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Model;

use Fisharebest\Webtrees\Individual;
use JsonSerializable;

/**
 * This class holds the data required to render and display the tree.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @link    https://github.com/magicsunday/webtrees-module-base/
 */
final class NodeData implements JsonSerializable
{
    /**
     * The unique ID of the individual.
     */
    private int $id = 0;

    /**
     * The XREF of the individual.
     */
    private string $xref = '';

    /**
     * The URL to this individual in webtrees.
     */
    private string $url = '';

    /**
     * The URL used to update the clicked entry in the tree with this
     * individual.
     */
    private string $updateUrl = '';

    /**
     * The generation the individual belongs to.
     */
    private int $generation = 0;

    /**
     * The full name of the individual.
     */
    private string $name = '';

    /**
     * TRUE if the name is written right to left.
     */
    private bool $isNameRtl = false;

    /**
     * The name as ordered segments, in display order.
     *
     * @var list<NameSegment>
     */
    private array $nameSegments = [];

    /**
     * The alternative name.
     */
    private string $alternativeName = '';

    /**
     * TRUE if the alternative name is written right to left.
     */
    private bool $isAltRtl = false;

    /**
     * The URL of the individuals highlight image.
     */
    private string $thumbnail = '';

    /**
     * The URL of the sex-specific silhouette image. Used as a client-side
     * onerror fallback when the highlight image's media file is missing.
     */
    private string $silhouette = '';

    /**
     * The sex of the individual.
     */
    private string $sex = 'U';

    /**
     * The formatted birthdate without HTML tags.
     */
    private string $birth = '';

    /**
     * The formatted death date without HTML tags.
     */
    private string $death = '';

    /**
     * The timespan label.
     */
    private string $timespan = '';

    /**
     * The facts of the chart box, one slot per row. Slot 0 is the birth row and slot 1 the death row,
     * the optional facts follow. A slot is null when the individual has no visible fact for it, so
     * every box of a chart keeps the same rows. The list stays empty when a chart shows no facts.
     *
     * @var list<FactView|null>
     */
    private array $facts = [];

    /**
     * The underlying individual instance. Only used internally.
     */
    private ?Individual $individual = null;

    /**
     * Sets the unique ID of the individual.
     *
     * @param int $id The unique ID
     *
     * @return NodeData
     */
    public function setId(int $id): NodeData
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Sets the XREF of the individual.
     *
     * @param string $xref The XREF
     *
     * @return NodeData
     */
    public function setXref(string $xref): NodeData
    {
        $this->xref = $xref;

        return $this;
    }

    /**
     * Sets the URL to the individual in webtrees.
     *
     * @param string $url The URL
     *
     * @return NodeData
     */
    public function setUrl(string $url): NodeData
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Sets the URL that updates the clicked entry in the tree with this individual.
     *
     * @param string $updateUrl The update URL
     *
     * @return NodeData
     */
    public function setUpdateUrl(string $updateUrl): NodeData
    {
        $this->updateUrl = $updateUrl;

        return $this;
    }

    /**
     * Sets the generation the individual belongs to.
     *
     * @param int $generation The generation
     *
     * @return NodeData
     */
    public function setGeneration(int $generation): NodeData
    {
        $this->generation = $generation;

        return $this;
    }

    /**
     * Sets the full name of the individual.
     *
     * @param string $name The full name
     *
     * @return NodeData
     */
    public function setName(string $name): NodeData
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Sets whether the name is written right to left.
     *
     * @param bool $isNameRtl TRUE if the name is written right to left
     *
     * @return NodeData
     */
    public function setIsNameRtl(bool $isNameRtl): NodeData
    {
        $this->isNameRtl = $isNameRtl;

        return $this;
    }

    /**
     * Sets the name as ordered segments, in display order.
     *
     * @param list<NameSegment> $nameSegments The name segments
     *
     * @return NodeData
     */
    public function setNameSegments(array $nameSegments): NodeData
    {
        $this->nameSegments = $nameSegments;

        return $this;
    }

    /**
     * Sets the alternative name.
     *
     * @param string $alternativeName The alternative name
     *
     * @return NodeData
     */
    public function setAlternativeName(string $alternativeName): NodeData
    {
        $this->alternativeName = $alternativeName;

        return $this;
    }

    /**
     * Sets whether the alternative name is written right to left.
     *
     * @param bool $isAltRtl TRUE if the alternative name is written right to left
     *
     * @return NodeData
     */
    public function setIsAltRtl(bool $isAltRtl): NodeData
    {
        $this->isAltRtl = $isAltRtl;

        return $this;
    }

    /**
     * Sets the URL of the highlight image of the individual.
     *
     * @param string $thumbnail The URL of the highlight image
     *
     * @return NodeData
     */
    public function setThumbnail(string $thumbnail): NodeData
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    /**
     * Sets the URL of the sex-specific silhouette image.
     *
     * @param string $silhouette The URL of the silhouette image
     *
     * @return NodeData
     */
    public function setSilhouette(string $silhouette): NodeData
    {
        $this->silhouette = $silhouette;

        return $this;
    }

    /**
     * Sets the sex of the individual.
     *
     * @param string $sex The sex
     *
     * @return NodeData
     */
    public function setSex(string $sex): NodeData
    {
        $this->sex = $sex;

        return $this;
    }

    /**
     * Sets the formatted birth date without HTML tags.
     *
     * @param string $birth The formatted birth date
     *
     * @return NodeData
     */
    public function setBirth(string $birth): NodeData
    {
        $this->birth = $birth;

        return $this;
    }

    /**
     * Sets the formatted death date without HTML tags.
     *
     * @param string $death The formatted death date
     *
     * @return NodeData
     */
    public function setDeath(string $death): NodeData
    {
        $this->death = $death;

        return $this;
    }

    /**
     * Sets the timespan label.
     *
     * @param string $timespan The timespan label
     *
     * @return NodeData
     */
    public function setTimespan(string $timespan): NodeData
    {
        $this->timespan = $timespan;

        return $this;
    }

    /**
     * Returns the facts of the chart box, one slot per row.
     *
     * @return list<FactView|null>
     */
    public function getFacts(): array
    {
        return $this->facts;
    }

    /**
     * Sets the facts of the chart box, one slot per row.
     *
     * @param list<FactView|null> $facts The facts, aligned with the rows of the chart
     *
     * @return NodeData
     */
    public function setFacts(array $facts): NodeData
    {
        $this->facts = $facts;

        return $this;
    }

    /**
     * Returns the individual the node was built from. It is held for the facade and
     * stays out of the serialised form.
     */
    public function getIndividual(): ?Individual
    {
        return $this->individual;
    }

    /**
     * Sets the individual the node was built from.
     *
     * @param Individual|null $individual The individual
     *
     * @return NodeData
     */
    public function setIndividual(?Individual $individual): NodeData
    {
        $this->individual = $individual;

        return $this;
    }

    /**
     * Returns the relevant data as an array. The facts are appended as the last key and only when
     * the list is not empty, so a consumer that never sets facts keeps its payload unchanged.
     *
     * @return array<string, int|bool|string|list<NameSegment>|list<FactView|null>>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'id'              => $this->id,
            'xref'            => $this->xref,
            'url'             => $this->url,
            'updateUrl'       => $this->updateUrl,
            'generation'      => $this->generation,
            'name'            => $this->name,
            'isNameRtl'       => $this->isNameRtl,
            'nameSegments'    => $this->nameSegments,
            'alternativeName' => $this->alternativeName,
            'isAltRtl'        => $this->isAltRtl,
            'thumbnail'       => $this->thumbnail,
            'silhouette'      => $this->silhouette,
            'sex'             => $this->sex,
            'birth'           => $this->birth,
            'death'           => $this->death,
            'timespan'        => $this->timespan,
        ];

        if ($this->facts !== []) {
            $data['facts'] = $this->facts;
        }

        return $data;
    }
}
