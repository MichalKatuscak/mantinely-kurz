#!/usr/bin/env bash
# Celé měření: stejné zadání, stejný výchozí stav, několik běhů po modelech.
# Každý běh má vlastní čistou kopii aplikace (beh.sh), nejvýš tři běží současně.
#
# Použití: bash mereni.sh <výstupní složka> <soubor se zadáním> <model:počet> [model:počet…]
#   např.  bash mereni.sh vysledky ../zadani.txt opus:3 sonnet:3
#
# Model je hodnota volby --model Claude Code (alias jako opus/sonnet/haiku, nebo celé ID).
# Výsledek: <výstupní složka>/r<N>-<model>/ s relace.jsonl, PRUBEH.md, diff.patch,
# make-check.txt, hranice.txt, zmeny.txt, cas.txt a zadani.txt.
set -uo pipefail
OUT="$1"; ZADANI="$2"; shift 2
SKRIPTY="$(cd "$(dirname "$0")" && pwd)"
mkdir -p "$OUT"

BEHY=()
for spec in "$@"; do
  m="${spec%%:*}"; n="${spec##*:}"
  for k in $(seq 1 "$n"); do BEHY+=("$m"); done
done

i=0
while [ $i -lt ${#BEHY[@]} ]; do
  for j in 0 1 2; do
    idx=$((i + j)); [ $idx -ge ${#BEHY[@]} ] && break
    m="${BEHY[$idx]}"; nazev="r$((idx + 1))-${m//[^a-zA-Z0-9.-]/_}"
    bash "$SKRIPTY/beh.sh" "$OUT/$nazev" "$ZADANI" --model "$m" > "$OUT/$nazev.log" 2>&1 &
  done
  wait
  i=$((i + 3))
done
cat "$OUT"/r*.log
echo "MĚŘENÍ HOTOVO"
