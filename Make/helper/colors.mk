# @see https://gist.github.com/rsperl/d2dfe88a520968fbc1f49db0a29345b9/

# =============================================================================
# Variables
# =============================================================================

# Define standard colors. Gated on `tput colors` actually succeeding, not
# just TERM being non-empty — an unknown terminal (e.g. TERM=bogus, common in
# minimal containers without a terminfo database) makes every other `tput`
# call below print "tput: unknown terminal" to stderr.
ifeq ($(shell tput colors >/dev/null 2>&1 && echo yes),yes)
	FBOLD         := $(shell tput bold)
	FDIM          := $(shell tput dim)
	FRESET        := $(shell tput sgr0)

	FBLACK        := $(shell tput setaf 0)
	FRED          := $(shell tput setaf 1)
	FGREEN        := $(shell tput setaf 2)
	FYELLOW       := $(shell tput setaf 3)
	FBLUE         := $(shell tput setaf 4)
	FPURPLE       := $(shell tput setaf 5)
	FCYAN         := $(shell tput setaf 6)
	FWHITE        := $(shell tput setaf 7)
else
	FBOLD         :=
	FDIM          :=
	FRESET        :=

	FBLACK        :=
	FRED          :=
	FGREEN        :=
	FYELLOW       :=
	FBLUE         :=
	FPURPLE       :=
	FCYAN         :=
	FWHITE        :=
	FRESET        :=
endif
