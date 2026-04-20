# =============================================================================
# CI / quality gates
# =============================================================================

#### CI / quality gates

.PHONY: ci-test ci-cgl ci-rector ci-phpstan-baseline

ci-test: .logo ## Run the full PHP CI suite (lint + phpstan + rector + phpunit + cgl).
	@$(COMPOSE_RUN) composer ci:test
	@echo -e "${FGREEN} ✔${FRESET} ci:test passed"

ci-cgl: .logo ## Run php-cs-fixer in fix mode.
	@$(COMPOSE_RUN) composer ci:cgl
	@echo -e "${FGREEN} ✔${FRESET} ci:cgl applied"

ci-rector: .logo ## Run rector in fix mode.
	@$(COMPOSE_RUN) composer ci:rector
	@echo -e "${FGREEN} ✔${FRESET} ci:rector applied"

ci-phpstan-baseline: .logo ## Regenerate phpstan-baseline.neon.
	@$(COMPOSE_RUN) composer ci:test:php:phpstan:baseline
	@echo -e "${FGREEN} ✔${FRESET} phpstan baseline regenerated"
