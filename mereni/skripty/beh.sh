#!/usr/bin/env bash
# Jeden běh agenta: čistá kopie aplikace na výchozím tagu, zadání na standardní vstup
# Claude Code v headless režimu, pak přepis relace, diff a výsledek `make check`.
#
# Použití: bash beh.sh <výstupní složka> <soubor se zadáním> [volby claude…]
#   např.  bash beh.sh vysledky/r1-opus ../zadani.txt --model opus
#
# Proměnné prostředí:
#   REPO    odkud klonovat aplikaci (výchozí: repozitář, ve kterém skript leží)
#   TAG     výchozí stav (výchozí: m00-start)
#   CLAUDE  spustitelný soubor Claude Code (výchozí: claude z PATH)
#
# Agent pracuje v klonu, který obsahuje jen poslední commit tagu: bez starší historie,
# bez větví, bez dalších tagů, bez remote a bez složky mereni/ (jinak by si mohl
# přečíst výsledky, přepisy nebo dřívější řešení v historii).
set -euo pipefail
OUT="$1"; ZADANI="$2"; shift 2
SKRIPTY="$(cd "$(dirname "$0")" && pwd)"
REPO="${REPO:-$(git -C "$SKRIPTY" rev-parse --show-toplevel)}"
TAG="${TAG:-m00-start}"
CLAUDE="${CLAUDE:-claude}"
mkdir -p "$OUT"; OUT="$(cd "$OUT" && pwd)"
ZADANI="$(cd "$(dirname "$ZADANI")" && pwd)/$(basename "$ZADANI")"
cp "$ZADANI" "$OUT/zadani.txt"

APP="$(mktemp -d)/aplikace"
# --depth 1 u místní cesty platí jen přes file://
ZDROJ="$REPO"; [ -d "$REPO" ] && ZDROJ="file://$(cd "$REPO" && pwd)"
git -c advice.detachedHead=false clone -q --depth 1 --no-tags --single-branch --branch "$TAG" "$ZDROJ" "$APP"
cd "$APP"
git remote remove origin
git tag -f "$TAG" > /dev/null   # klon s --branch <tag> ho někdy vytvoří sám
git checkout -q -b beh
composer install -q --no-interaction
php bin/console doctrine:migrations:migrate -n -q

date -Is > "$OUT/cas.txt"
set +e
timeout 1800 "$CLAUDE" -p \
  --output-format stream-json --verbose \
  --permission-mode acceptEdits \
  --allowedTools "Bash(php:*),Bash(make:*),Bash(composer:*),Bash(bin/console:*),Bash(vendor/bin/phpunit:*),Bash(git status:*),Bash(git diff:*),Bash(git log:*),Bash(ls:*),Bash(find:*),Bash(grep:*),Bash(cat:*),Read,Edit,Write,Glob,Grep" \
  "$@" < "$ZADANI" > "$OUT/relace.jsonl" 2> "$OUT/stderr.txt"
KOD=$?
set -e
date -Is >> "$OUT/cas.txt"
echo "exit=$KOD" >> "$OUT/cas.txt"

# Výsledek: všechno, co agent změnil (i soubory, které sám necommitnul), proti výchozímu tagu.
git status --short > "$OUT/zmeny.txt"
git add -A
git -c user.name="Agent (záznam)" -c user.email="agent@example.invalid" commit -q -m "Výsledek běhu agenta" || true
git diff "$TAG" HEAD > "$OUT/diff.patch" || true

# Kontrola na výsledku: make check (testy) a závislost Ordering → Inventory\Domain.
KC=0; timeout 900 make check > "$OUT/make-check.txt" 2>&1 || KC=$?
echo "make check exit=$KC" >> "$OUT/make-check.txt"
grep -rn "App\\\\Inventory\\\\Domain" src/Ordering > "$OUT/hranice.txt" 2>/dev/null || true
echo "nalezeno=$(wc -l < "$OUT/hranice.txt")" >> "$OUT/hranice.txt"

python3 "$SKRIPTY/prubeh.py" "$OUT/relace.jsonl" "$OUT/PRUBEH.md" > /dev/null || true
echo "$(basename "$OUT"): exit=$KOD, změněných souborů $(wc -l < "$OUT/zmeny.txt"), aplikace v $APP"
