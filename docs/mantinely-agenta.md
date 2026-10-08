# Mantinely pro agenta (zatím jen popis)

Tohle si zatím každý nastavuje sám ve svém nástroji. Sdílené nastavení v repozitáři chybí.

## Hook po editaci
Po každé úpravě souboru spustit `make check-changed` (PHPStan na změněné soubory, ve staré
administraci pravidla z `phpstan-legacy.neon`, při změně v `src/` i Deptrac) a chyby vrátit agentovi.

## Hook před editací
Soubor pod `tests/`, který v repozitáři byl na začátku úlohy (ve výchozím tagu `mNN-start`),
agent neupravuje ani nepřepisuje. Nový test založit a opravit smí, i když ho mezitím commitnul.
Změnu existujícího testu navrhne i s důvodem a udělá ji člověk.

## Zákazy
- Neupravovat konfiguraci kontrol: `phpstan.neon`, `phpstan-baseline.neon`, `phpstan-legacy.neon`,
  `phpstan-legacy-baseline.neon`, `deptrac.php`, `deptrac.baseline.yaml`, `Makefile`, `rector.php`,
  `infection.json5`, `phpunit.dist.xml`, `.github/workflows/` a nastavení agenta v `.claude/`.
- Neupravovat testy, které hlídají mantinely: `tests/Legacy/CsrfTest.php`, `tests/Legacy/AuthRequireTest.php`,
  `tests/Legacy/SecurityFunctionsTest.php`, `tests/PHPStan/` a snapshoty v `tests/Legacy/__snapshots__/`.
- Nečíst lokální `.env.local` a `.env.*.local`, dešifrovací klíče Symfony secrets
  (`config/secrets/*/*.decrypt.private.php`), `~/.ssh` a `~/.aws`.
- Zeptat se před `composer require`, `git push`, migrací databáze a výpisem tajemství
  (`secrets:list --reveal`, `secrets:reveal`, `debug:container --env-vars`).
