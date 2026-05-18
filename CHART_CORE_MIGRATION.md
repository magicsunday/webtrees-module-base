# Shared Chart-Core Migration Notes (WEB-18)

This document tracks the shared extraction introduced for `webtrees-pedigree-chart`, `webtrees-fan-chart`, and `webtrees-descendants-chart`.

## Extracted Shared Core

The following logic now lives in `magicsunday/webtrees-module-base` and is consumed by all three modules:

- `src/Traits/ModuleChartTrait.php`
  - shared `chartBoxMenu()` and `chartUrl()` behavior
- `src/Traits/ModuleCustomTrait.php`
  - shared custom module metadata + translation loading
- `src/Facade/RouteAwareDataFacadeTrait.php`
  - shared DataFacade route/module setters and URL helper
- `src/Support/TextDirection.php`
  - shared RTL detection helper

## JS chart-core follow-up (WEB-21)

The page bootstrap helpers are now shared from `@magicsunday/webtrees-chart-lib/chart-core`:

- `buildChartAjaxUrl()` for shared URL/query assembly (xref + typed query params)
- `syncCollapseToggle()` for persisted "show more options" collapse state
- `setChartAjaxUrl()` for `data-wt-ajax-url` container wiring
- `setChartOptionsGlobal()` for UMD namespace chartOptions publishing

Consumers migrated:

- `webtrees-pedigree-chart/resources/js/modules/page-init.js`
- `webtrees-fan-chart/resources/js/modules/page-init.js`
- `webtrees-descendants-chart/resources/js/modules/page-init.js`

## Asset Version Strategy

Versioned JS bundles are still generated from `package.json` version fields by Rollup (`*-<version>.min.js`).  
To avoid manual drift between PHP and JS references, release automation updates both:

- `src/Module.php` `CUSTOM_VERSION`
- `package.json` `version`

in the same release step before asset build and packaging.

## Smoke Checklist

Run one happy path per module after build/install:

1. Pedigree chart
   - Open a person, render the chart page, click an ancestor to recenter, and confirm chart reload works.
2. Fan chart
   - Open a person, render chart, toggle at least one UI option (for example `showPlaces`), then click an ancestor and confirm state is preserved.
3. Descendants chart
   - Open a person, render chart, click a descendant branch node to recenter, and confirm updated tree renders without JS errors.

## Rollback Procedure

If regressions appear after this extraction:

1. Revert the commits that introduced the shared module-base traits/helpers.
2. Restore module-local implementations of:
   - `Traits/ModuleChartTrait.php`
   - `Traits/ModuleCustomTrait.php`
   - DataFacade route/RTL helper methods
3. Rebuild JS assets (`make build`) and rerun module smoke checks.

If the JS chart-core follow-up regresses:

1. Revert the `@magicsunday/webtrees-chart-lib/chart-core` helper import changes in each module `page-init.js`.
2. Restore module-local page bootstrap functions (`getUrl`, collapse toggle handler, namespace assignment).
3. Pin all three modules back to the prior chart-lib tag and rebuild page bundles.
