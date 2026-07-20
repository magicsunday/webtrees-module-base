SHELL = /bin/bash

.SILENT:

# Do not print "Entering directory ..."
MAKEFLAGS += --no-print-directory

.PHONY: no_targets__ *
	no_targets__:

.DEFAULT_GOAL := help

# Includes
-include Make/*.mk
-include Make/**/*.mk

# Argument fix workaround
%:
	@:
