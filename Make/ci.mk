# =============================================================================
# Removed targets — CI / quality gates
# =============================================================================
#
# This repo has no local PHP container (see AGENTS.md "Running the test
# suite") — these used to run @$(COMPOSE_RUN) composer ... against a now-
# removed compose `php` service. Fail loudly instead of the catch-all `%:`
# rule silently reporting success.

.PHONY: ci-test ci-cgl ci-rector ci-phpstan-baseline

ci-test:
	@echo -e "${FRED} ✖${FRESET} Removed — this repo has no local PHP container."
	@echo -e "   Run instead: cd <webtrees-docker root> && docker compose run --rm buildbox bash -c 'cd /var/webtrees/app/vendor/magicsunday/webtrees-module-base && composer ci:test'"
	@exit 1

ci-cgl:
	@echo -e "${FRED} ✖${FRESET} Removed — this repo has no local PHP container."
	@echo -e "   Run instead: cd <webtrees-docker root> && docker compose run --rm buildbox bash -c 'cd /var/webtrees/app/vendor/magicsunday/webtrees-module-base && composer ci:cgl'"
	@exit 1

ci-rector:
	@echo -e "${FRED} ✖${FRESET} Removed — this repo has no local PHP container."
	@echo -e "   Run instead: cd <webtrees-docker root> && docker compose run --rm buildbox bash -c 'cd /var/webtrees/app/vendor/magicsunday/webtrees-module-base && composer ci:rector'"
	@exit 1

ci-phpstan-baseline:
	@echo -e "${FRED} ✖${FRESET} Removed — the phpstan baseline was retired; no composer script regenerates it (see AGENTS.md \"Common pitfalls\")."
	@exit 1
