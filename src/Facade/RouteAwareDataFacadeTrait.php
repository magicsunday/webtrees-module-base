<?php

/**
 * This file is part of the package magicsunday/webtrees-module-base.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Webtrees\ModuleBase\Facade;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use MagicSunday\Webtrees\ModuleBase\Contract\ModuleAssetUrlInterface;
use MagicSunday\Webtrees\ModuleBase\Support\TextDirection;

/**
 * Shared route/module helpers for chart DataFacade implementations.
 */
trait RouteAwareDataFacadeTrait
{
    private ModuleCustomInterface&ModuleAssetUrlInterface $module;

    private string $route;

    /**
     * @return static
     */
    public function setModule(ModuleCustomInterface&ModuleAssetUrlInterface $module): static
    {
        $this->module = $module;

        return $this;
    }

    /**
     * @return static
     */
    public function setRoute(string $route): static
    {
        $this->route = $route;

        return $this;
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function chartUrl(Individual $individual, array $parameters = []): string
    {
        return route(
            $this->route,
            [
                'xref' => $individual->xref(),
                'tree' => $individual->tree()->name(),
            ] + $parameters
        );
    }

    /**
     * Returns whether the given text is in RTL style or not.
     */
    private function isRtl(string $text): bool
    {
        return TextDirection::isRtl($text);
    }
}
