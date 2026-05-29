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

/**
 * Shared route/module helpers for chart DataFacade implementations.
 *
 * Extends ModuleAwareDataFacadeTrait with a route reference and a canonical
 * chartUrl() builder for consumers that drive their AJAX updates through the
 * chart's own routed URL.
 */
trait RouteAwareDataFacadeTrait
{
    use ModuleAwareDataFacadeTrait;

    private string $route;

    /**
     * Sets the canonical chart route used by chartUrl().
     *
     * @return static
     */
    public function setRoute(string $route): static
    {
        $this->route = $route;

        return $this;
    }

    /**
     * Builds the canonical chart URL for an individual, optionally with extra
     * query parameters (e.g. layout / generations toggles).
     *
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
}
