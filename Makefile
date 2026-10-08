# Všechny kontroly projektu spouští jeden příkaz: make check
.PHONY: check check-changed test test-domain infection infection-full phpstan phpstan-legacy rector deptrac symfony

# Mutační testy běží jen na řádcích změněných od posledního tagu cvičení
# (mNN-start). Jiný základ: make check INFECTION_BASE=main
INFECTION_BASE ?= $(shell git describe --tags --abbrev=0 --match 'm[0-9][0-9]-start' 2>/dev/null || echo HEAD)

PHPSTAN ?= vendor/bin/phpstan
PHPSTAN_FLAGS ?=
RECTOR_FLAGS ?=

check: test infection phpstan phpstan-legacy rector deptrac symfony

test:
	vendor/bin/phpunit --no-progress

# Rychlá vnitřní smyčka: doménové testy bez jádra Symfony a bez databáze.
test-domain:
	vendor/bin/phpunit --no-progress --testsuite domain

# Změněné řádky, ve kterých Infection nenajde nic k mutaci (třeba jen volání),
# práh MSI neshodí.
infection:
	@if git diff --quiet $(INFECTION_BASE) -- src; then \
		echo "Infection: žádné změněné řádky v src/ od $(INFECTION_BASE)"; \
	else \
		vendor/bin/infection --git-diff-lines --git-diff-base=$(INFECTION_BASE) \
			--threads=1 --no-progress --show-mutations --min-covered-msi=80 \
			--ignore-msi-with-no-mutations; \
	fi

# Celý běh Infection (CI jednou týdně).
infection-full:
	vendor/bin/infection --threads=1 --no-progress

# PHPStan potřebuje XML kontejneru (phpstan-symfony), proto nejdřív cache:warmup.
phpstan:
	@bin/console cache:warmup --quiet
	@$(PHPSTAN) analyse --no-progress --error-format=raw --memory-limit=1G $(PHPSTAN_FLAGS) && echo "PHPStan: bez chyb"

# Stará administrace: jen pravidla na nebezpečné vzory (zápis do orders/stock_items,
# hodnoty přilepené do SQL). Staré výskyty jsou v baseline, hlásí se jen nové.
phpstan-legacy:
	@$(PHPSTAN) analyse -c phpstan-legacy.neon --no-progress --error-format=raw --memory-limit=1G $(PHPSTAN_FLAGS) && echo "PHPStan (stará administrace): bez nových nebezpečných vzorů"

# Rector nanečisto: selže, když by v kódu něco přepsal.
rector:
	@vendor/bin/rector process --dry-run --no-progress-bar $(RECTOR_FLAGS)

# Hranice mezi ohraničenými kontexty. Třída mimo všechny vrstvy je chyba
# (--report-uncovered vypíše, která závislost to je).
deptrac:
	@vendor/bin/deptrac analyse --fail-on-uncovered --report-uncovered --no-progress

# Kontejner a mapování Doctrine (bez porovnání s databází).
symfony:
	@bin/console lint:container
	@bin/console doctrine:schema:validate --skip-sync

# Rychlá kontrola po editaci: PHPStan jen na změněné a nové soubory. Volají ji hooky
# všech nástrojů. Chyby jdou na stderr a make při chybě končí kódem 2, takže je agent
# dostane zpátky jako zpětnou vazbu. Změněné soubory staré administrace kontroluje
# phpstan-legacy.neon (jen nebezpečné vzory, staré výskyty v baseline). Při změně
# v src/ běží i Deptrac (hranice platí i pro starou administraci).
check-changed:
	@changed=$$( { git diff --name-only --diff-filter=d HEAD; git ls-files --others --exclude-standard; } \
		| grep -E '^(src|tests|tools)/.*\.php$$' | sort -u ); \
	files=$$(echo "$$changed" | grep -v '^src/Legacy/'); \
	legacy=$$(echo "$$changed" | grep '^src/Legacy/' | grep -v '^src/Legacy/templates/'); \
	status=0; \
	if [ -n "$$legacy" ]; then \
		$(PHPSTAN) analyse -c phpstan-legacy.neon --no-progress --error-format=raw --memory-limit=1G $(PHPSTAN_FLAGS) $$legacy 1>&2 || status=2; \
	fi; \
	if [ -n "$$files" ]; then \
		bin/console cache:warmup --quiet; \
		$(PHPSTAN) analyse --no-progress --error-format=raw --memory-limit=1G $(PHPSTAN_FLAGS) $$files 1>&2 || status=2; \
	fi; \
	if echo "$$changed" | grep -q '^src/'; then \
		vendor/bin/deptrac analyse --fail-on-uncovered --report-uncovered --no-progress 1>&2 || status=2; \
	fi; \
	exit $$status
