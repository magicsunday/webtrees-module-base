# =============================================================================
# Local development helpers (PHP-only library — no JS/build pipeline)
# =============================================================================

#### Composer

.PHONY: install update clean

install: .logo ## Install composer dependencies into .build/vendor.
	@$(COMPOSE_RUN) composer install --no-progress --no-interaction
	@echo -e "${FGREEN} ✔${FRESET} Composer dependencies installed"

update: .logo ## Update composer dependencies.
	@$(COMPOSE_RUN) composer update --no-progress --no-interaction
	@echo -e "${FGREEN} ✔${FRESET} Composer dependencies updated"

clean: .logo ## Remove .build/ (vendor + caches).
	@rm -rf .build
	@echo -e "${FGREEN} ✔${FRESET} .build/ removed"

#### Container

.PHONY: bash

bash: .logo ## Open an interactive shell in the php container.
	@$(COMPOSE_BIN) run --rm php sh
