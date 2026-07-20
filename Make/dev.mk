# =============================================================================
# Local development helpers (PHP-only library — no JS/build pipeline)
# =============================================================================

#### Clean

.PHONY: clean

clean: .logo ## Remove .build/ and node_modules/ (vendor, caches, npm/jscpd artifacts).
	@removed=; \
	for path in $(MAKEFILE_DIR)/.build $(MAKEFILE_DIR)/node_modules $(MAKEFILE_DIR)/package.json $(MAKEFILE_DIR)/package-lock.json; do \
		if [ -e "$$path" ] || [ -L "$$path" ]; then \
			rm -rf "$$path" && removed="$$removed $$path"; \
		fi; \
	done; \
	if [ -n "$$removed" ]; then \
		echo -e "${FGREEN} ✔${FRESET} removed:$$removed"; \
	else \
		echo -e "${FGREEN} ✔${FRESET} .build/ and node_modules/ already absent"; \
	fi

# =============================================================================
# Removed targets
# =============================================================================
#
# This repo has no local PHP container (see AGENTS.md "Running the test
# suite") — install/update/bash and the CI/quality-gate targets used to run
# through a now-removed compose `php` service. Fail loudly instead of letting
# an unmatched target silently report success.

#### Removed targets

define REMOVED_TARGET
	@echo -e "${FRED} ✖${FRESET} Removed — this repo has no local PHP container."
	@echo -e "   Run instead: cd /path/to/webtrees-docker && docker compose run --rm buildbox bash -c 'cd /var/webtrees/app/vendor/magicsunday/webtrees-module-base && $(1)'"
	@exit 1
endef

.PHONY: install update bash ci-test ci-cgl ci-rector ci-phpstan-baseline

install: .logo ## Removed — run composer install via the buildbox container instead.
	$(call REMOVED_TARGET,composer install)

update: .logo ## Removed — run composer update via the buildbox container instead.
	$(call REMOVED_TARGET,composer update)

bash: .logo ## Removed — open a shell in the buildbox container instead.
	$(call REMOVED_TARGET,exec bash)

ci-test: .logo ## Removed — run composer ci:test via the buildbox container instead.
	$(call REMOVED_TARGET,composer ci:test)

ci-cgl: .logo ## Removed — run composer ci:cgl via the buildbox container instead.
	$(call REMOVED_TARGET,composer ci:cgl)

ci-rector: .logo ## Removed — run composer ci:rector via the buildbox container instead.
	$(call REMOVED_TARGET,composer ci:rector)

ci-phpstan-baseline: .logo ## Removed — the phpstan baseline was retired, no replacement command.
	@echo -e "${FRED} ✖${FRESET} Removed — the phpstan baseline was retired; no composer script regenerates it (see AGENTS.md \"Code style\")."
	@exit 1
