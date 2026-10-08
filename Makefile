# Všechny kontroly projektu spouští jeden příkaz: make check
.PHONY: check test test-domain infection infection-full

# Mutační testy běží jen na řádcích změněných od posledního tagu cvičení
# (mNN-start). Jiný základ: make check INFECTION_BASE=main
INFECTION_BASE ?= $(shell git describe --tags --abbrev=0 --match 'm[0-9][0-9]-start' 2>/dev/null || echo HEAD)

check: test infection

test:
	vendor/bin/phpunit --no-progress

# Rychlá vnitřní smyčka: doménové testy bez jádra Symfony a bez databáze.
test-domain:
	vendor/bin/phpunit --no-progress --testsuite domain

infection:
	@if git diff --quiet $(INFECTION_BASE) -- src; then \
		echo "Infection: žádné změněné řádky v src/ od $(INFECTION_BASE)"; \
	else \
		vendor/bin/infection --git-diff-lines --git-diff-base=$(INFECTION_BASE) \
			--threads=1 --no-progress --show-mutations --min-covered-msi=80; \
	fi

# Celý běh Infection (CI jednou týdně).
infection-full:
	vendor/bin/infection --threads=1 --no-progress
