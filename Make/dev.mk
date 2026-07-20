# =============================================================================
# Local development helpers (PHP-only library — no JS/build pipeline)
# =============================================================================

#### Composer

.PHONY: clean

clean: .logo ## Remove .build/ (vendor + caches).
	@rm -rf .build
	@echo -e "${FGREEN} ✔${FRESET} .build/ removed"
