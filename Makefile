# Všechny kontroly projektu spouští jeden příkaz: make check
.PHONY: check check-changed test test-domain infection infection-full phpstan

# Mutační testy běží jen na řádcích změněných od posledního tagu cvičení
# (mNN-start). Jiný základ: make check INFECTION_BASE=main
INFECTION_BASE ?= $(shell git describe --tags --abbrev=0 --match 'm[0-9][0-9]-start' 2>/dev/null || echo HEAD)

PHPSTAN ?= vendor/bin/phpstan
PHPSTAN_FLAGS ?=

check: test infection phpstan

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

# PHPStan potřebuje XML kontejneru (phpstan-symfony), proto nejdřív cache:warmup.
phpstan:
	@bin/console cache:warmup --quiet
	@$(PHPSTAN) analyse --no-progress --error-format=raw --memory-limit=1G $(PHPSTAN_FLAGS) && echo "PHPStan: bez chyb"

# Rychlá kontrola po editaci: PHPStan jen na změněné a nové soubory. Volají ji hooky
# všech nástrojů. Chyby jdou na stderr a make při chybě končí kódem 2, takže je agent
# dostane zpátky jako zpětnou vazbu.
check-changed:
	@files=$$( { git diff --name-only --diff-filter=d HEAD; git ls-files --others --exclude-standard; } \
		| grep -E '^(src|tests|tools)/.*\.php$$' | grep -v '^src/Legacy/' | sort -u ); \
	if [ -z "$$files" ]; then exit 0; fi; \
	bin/console cache:warmup --quiet; \
	$(PHPSTAN) analyse --no-progress --error-format=raw --memory-limit=1G $(PHPSTAN_FLAGS) $$files 1>&2
