# =============================================================================
# Local development helpers (PHP-only library — no JS/build pipeline)
# =============================================================================

#### Composer

.PHONY: clean

clean: .logo ## Remove .build/ (vendor + caches).
	@rm -rf $(MAKEFILE_DIR)/.build
	@echo -e "${FGREEN} ✔${FRESET} .build/ removed"

# =============================================================================
# Removed targets
# =============================================================================
#
# This repo has no local PHP container (see AGENTS.md "Running the test
# suite") — install/update ran through a now-removed compose `php` service.
# Fail loudly instead of the catch-all `%:` rule silently reporting success.

.PHONY: install update bash

install:
	@echo -e "${FRED} ✖${FRESET} Removed — this repo has no local PHP container."
	@echo -e "   Run instead: cd <webtrees-docker root> && docker compose run --rm buildbox bash -c 'cd /var/webtrees/app/vendor/magicsunday/webtrees-module-base && composer install'"
	@exit 1

update:
	@echo -e "${FRED} ✖${FRESET} Removed — this repo has no local PHP container."
	@echo -e "   Run instead: cd <webtrees-docker root> && docker compose run --rm buildbox bash -c 'cd /var/webtrees/app/vendor/magicsunday/webtrees-module-base && composer update'"
	@exit 1

bash:
	@echo -e "${FRED} ✖${FRESET} Removed — this repo has no local PHP container."
	@echo -e "   Run instead: cd <webtrees-docker root> && docker compose run --rm buildbox bash, then cd app/vendor/magicsunday/webtrees-module-base"
	@exit 1
