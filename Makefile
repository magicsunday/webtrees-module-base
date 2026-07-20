SHELL = /bin/bash

.SILENT:

# Do not print "Entering directory ..."
MAKEFLAGS += --no-print-directory

.DEFAULT_GOAL := help

# Directory this Makefile lives in, so included files and their recipes
# resolve correctly regardless of the caller's working directory (e.g.
# `make -f /abs/path/Makefile <target>` invoked from elsewhere).
MAKEFILE_DIR := $(patsubst %/,%,$(dir $(lastword $(MAKEFILE_LIST))))

# Includes
-include $(MAKEFILE_DIR)/Make/*.mk
-include $(MAKEFILE_DIR)/Make/**/*.mk

# A renamed/sparse/stray checkout that loses Make/ would otherwise silently
# fall back to "No rule to make target" for every real target without
# explaining why — make the missing includes loud instead. dev.mk provides the
# quality-gate targets, helper/ui.mk provides .logo (a dependency of nearly
# every target), and helper/help.mk provides the default `help` goal — all
# three are equally load-bearing.
REQUIRED_MAKE_INCLUDES := Make/dev.mk Make/helper/ui.mk Make/helper/help.mk
MISSING_MAKE_INCLUDES  := $(filter-out $(wildcard $(addprefix $(MAKEFILE_DIR)/,$(REQUIRED_MAKE_INCLUDES))),$(addprefix $(MAKEFILE_DIR)/,$(REQUIRED_MAKE_INCLUDES)))
$(if $(MISSING_MAKE_INCLUDES),$(error Make/ includes are missing — incomplete checkout? Missing: $(MISSING_MAKE_INCLUDES)))
