#!/usr/bin/env bash
# Druhá zpráva zadavatele do stejné relace (měření 12.1, změna protokolu z 8. 10. 2026).
# Každý běh dostane stejný text ze souboru se zprávou, ve stejné kopii aplikace a se stejnými
# volbami Claude Code jako v beh.sh. Výsledek první fáze zůstane uložený s příponou -1.
#
# Použití: bash pokracovani.sh <složka běhu> <soubor se zprávou> [volby claude…]
#   např.  bash pokracovani.sh vysledky/r1-opus ../odpoved-12-1.txt --model opus
# Kopie aplikace se najde v <složka běhu>.log (řádek „aplikace v …“ z beh.sh).
set -euo pipefail
OUT="$(cd "$1" && pwd)"; ZPRAVA="$(cd "$(dirname "$2")" && pwd)/$(basename "$2")"; shift 2
SKRIPTY="$(cd "$(dirname "$0")" && pwd)"
CLAUDE="${CLAUDE:-claude}"
APP="$(grep -o 'aplikace v .*' "$OUT.log" | tail -1 | sed 's/^aplikace v //')"
[ -d "$APP" ] || { echo "$(basename "$OUT"): kopie aplikace $APP neexistuje" >&2; exit 1; }
RELACE="$(python3 -c "import json,sys
for l in open(sys.argv[1],encoding='utf-8'):
    try: e=json.loads(l)
    except Exception: continue
    if e.get('session_id'): print(e['session_id']); break" "$OUT/relace.jsonl")"
TAG="$(git -C "$APP" describe --tags --abbrev=0)"

# první fáze stranou
for f in diff.patch zmeny.txt make-check.txt hranice.txt PRUBEH.md cas.txt stderr.txt; do
  [ -e "$OUT/$f" ] && mv "$OUT/$f" "$OUT/${f%.*}-1.${f##*.}"
done
cp "$ZPRAVA" "$OUT/zprava-2.txt"

cd "$APP"
date -Is > "$OUT/cas.txt"
set +e
timeout 1800 "$CLAUDE" -p --resume "$RELACE" \
  --output-format stream-json --verbose \
  --permission-mode acceptEdits \
  --allowedTools "Bash(php:*),Bash(make:*),Bash(composer:*),Bash(bin/console:*),Bash(vendor/bin/phpunit:*),Bash(git status:*),Bash(git diff:*),Bash(git log:*),Bash(ls:*),Bash(find:*),Bash(grep:*),Bash(cat:*),Read,Edit,Write,Glob,Grep" \
  "$@" < "$ZPRAVA" > "$OUT/relace-2.jsonl" 2> "$OUT/stderr.txt"
KOD=$?
set -e
date -Is >> "$OUT/cas.txt"
echo "exit=$KOD" >> "$OUT/cas.txt"

# výsledek po druhé zprávě proti výchozímu tagu, stejně jako v beh.sh
git status --short > "$OUT/zmeny.txt"
git add -A
git -c user.name="Agent (záznam)" -c user.email="agent@example.invalid" commit -q -m "Výsledek běhu agenta po druhé zprávě" || true
git diff "$TAG" HEAD > "$OUT/diff.patch" || true
git diff --stat "$TAG" HEAD > "$OUT/zmeny-celkem.txt" || true
KC=0; timeout 900 make check > "$OUT/make-check.txt" 2>&1 || KC=$?
echo "make check exit=$KC" >> "$OUT/make-check.txt"
grep -rn "App\\\\Inventory\\\\Domain" src/Ordering > "$OUT/hranice.txt" 2>/dev/null || true
echo "nalezeno=$(wc -l < "$OUT/hranice.txt")" >> "$OUT/hranice.txt"
python3 "$SKRIPTY/prubeh.py" "$OUT/relace-2.jsonl" "$OUT/PRUBEH.md" > /dev/null || true
echo "$(basename "$OUT"): druhá zpráva exit=$KOD, změněných souborů celkem $(grep -c . "$OUT/zmeny-celkem.txt" || true)"
