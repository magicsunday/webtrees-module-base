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
