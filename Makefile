# Všechny kontroly projektu spouští jeden příkaz: make check
.PHONY: check test

check: test

test:
	vendor/bin/phpunit --no-progress
