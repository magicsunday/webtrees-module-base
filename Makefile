SHELL = /bin/bash

.SILENT:

# Do not print "Entering directory ..."
MAKEFLAGS += --no-print-directory

.PHONY: no_targets__ *
	no_targets__:

.DEFAULT_GOAL := help

# Directory this Makefile lives in, so included files and their recipes
# resolve correctly regardless of the caller's working directory (e.g.
# `make -f /abs/path/Makefile <target>` invoked from elsewhere).
MAKEFILE_DIR := $(patsubst %/,%,$(dir $(lastword $(MAKEFILE_LIST))))

# Includes
-include $(MAKEFILE_DIR)/Make/*.mk
-include $(MAKEFILE_DIR)/Make/**/*.mk

# Argument fix workaround
%:
	@:
