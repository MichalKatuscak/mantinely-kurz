#!/usr/bin/env bash
# Jeden běh ablačního doměření 12.1: kopie varianty, první fáze (zadání) a hned po ní druhá
# zpráva zadavatele do stejné relace. Převzato z beh.sh a pokracovani.sh měření 12.1: stejné
# volby Claude Code, stejné povolené nástroje, stejné výstupy a jejich jména.
#
# Použití (WSL): bash beh-ablace.sh <id běhu> <varianta> <model>
#   např.  bash beh-ablace.sh r01-opus A claude-opus-5-5
# Obvykle ho volá fronta.sh podle fronta.txt.
#
# Rozdíly proti 12.1 (zdůvodnění v PROTOKOL-ABLACE.md):
# - kopie z priprav-variantu.sh, ne z beh.sh (stejný postup, navíc změna varianty);
# - kopie v ~/kopie/tmp.XXXXXXXXXX/aplikace místo /tmp (WSL při spuštění maže /tmp),
#   cesta nic neprozrazuje, agent ji vidí jako pracovní složku;
# - Claude Code připnutý na 2.1.291 (CLAUDE), plné ID modelu, DISABLE_AUTOUPDATER=1;
# - druhá zpráva jde hned po první fázi (ve 12.1 po přestávce, viz změna protokolu);
# - záznam výstupu hooku make check-changed: obal make v PATH (mimo kopii) zapíše výstup
#   check-changed do senzory-1.log / senzory.log. Agent dostane stejný stdout, stderr
#   i návratový kód. Ostatní volání make obal předá skutečnému make beze změny (exec).
#
# Výstup: data/<id>/ se stejnými soubory jako běhy 12.1 (relace.jsonl, relace-2.jsonl,
# PRUBEH-1.md, PRUBEH.md, diff-1.patch, diff.patch, make-check-1.txt, make-check.txt,
# hranice-1.txt, hranice.txt, zmeny-1.txt, zmeny.txt, zmeny-celkem.txt, cas-1.txt, cas.txt,
# stderr-1.txt, stderr.txt, zadani.txt, zprava-2.txt) a navíc senzory-1.log, senzory.log,
# beh.txt (varianta, model, verze, kopie) a STAV (HOTOVO nebo NEPLATNY s důvodem).
# Návratový kód: 0 HOTOVO, 3 NEPLATNY, jiný = chyba přípravy.
set -euo pipefail
ID="${1:?id běhu}"; V="${2:?varianta}"; MODEL="${3:?model}"
DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
DATA="${DATA:-$DIR/data}"
OUT="$DATA/$ID"
VERZE=2.1.291
CLAUDE="${CLAUDE:-$HOME/.local/share/claude/versions/$VERZE}"
KOPIE="${KOPIE:-$HOME/kopie}"
ZADANI="$DIR/vstupy/zadani.txt"
ZPRAVA="$DIR/vstupy/odpoved-12-1.txt"
NASTROJE="Bash(php:*),Bash(make:*),Bash(composer:*),Bash(bin/console:*),Bash(vendor/bin/phpunit:*),Bash(git status:*),Bash(git diff:*),Bash(git log:*),Bash(ls:*),Bash(find:*),Bash(grep:*),Bash(cat:*),Read,Edit,Write,Glob,Grep"

# vstupy bajt po bajtu jako ve 12.1
echo "99c1e8fb5f7639d0f7b8f0c92d016eff26d88a979085c6b184ffaa5a4b78d70f  $ZADANI
d030c513f9297509e4e4949c5a48ca97ab7d1624a7a204ac0ee18c78b7db0d22  $ZPRAVA" | sha256sum -c --quiet
[[ "$("$CLAUDE" --version 2>/dev/null)" == "$VERZE "* ]] || { echo "$CLAUDE není Claude Code $VERZE" >&2; exit 1; }
[ -e "$OUT" ] && { echo "$OUT už existuje (fronta.sh ho před opakováním přesune)" >&2; exit 1; }

mkdir -p "$OUT" "$KOPIE"
OUT="$(cd "$OUT" && pwd)"
cp "$ZADANI" "$OUT/zadani.txt"
ROOT="$(mktemp -d -p "$KOPIE")"
APP="$ROOT/aplikace"
{ echo "id=$ID"; echo "varianta=$V"; echo "model=$MODEL"; echo "claude=$CLAUDE"; echo "kopie=$APP"; } > "$OUT/beh.txt"
bash "$DIR/priprav-variantu.sh" "$V" "$APP" --bez-kontroly > "$OUT/priprava.txt" 2>&1
TAG=m12-start

# obal make: zaznamená výstup check-changed (hook po každé editaci), jinak skutečný make
OBAL="$ROOT/obal"; mkdir -p "$OBAL"
MAKE_SKUTECNY="$(command -v make)"
cat > "$OBAL/make" <<EOF
#!/usr/bin/env bash
case " \$* " in *" check-changed "*) ;; *) exec "$MAKE_SKUTECNY" "\$@" ;; esac
o="\$(mktemp)"; e="\$(mktemp)"
"$MAKE_SKUTECNY" "\$@" > "\$o" 2> "\$e"; k=\$?
cat "\$o"; cat "\$e" >&2
{ echo "=== \$(date -Is) make \$* (exit \$k)"; echo "--- stdout"; cat "\$o"; echo "--- stderr"; cat "\$e"; } >> "$OBAL/senzory.log"
rm -f "\$o" "\$e"
exit \$k
EOF
chmod +x "$OBAL/make"

# platnost fáze: model a verze ze záznamu init, řádek result bez chyby (timeout = platný výsledek)
platnost() {  # $1 relace.jsonl, $2 návratový kód
  python3 - "$1" "$2" "$MODEL" "$VERZE" <<'PY'
import json, sys
src, kod, model, verze = sys.argv[1], int(sys.argv[2]), sys.argv[3], sys.argv[4]
init = res = None
for l in open(src, encoding='utf-8', errors='replace'):
    try: e = json.loads(l)
    except Exception: continue
    if e.get('type') == 'system' and e.get('subtype') == 'init' and init is None: init = e
    if e.get('type') == 'result': res = e
if init is None: print('NEPLATNY chybí záznam init'); sys.exit()
if init.get('model') != model: print(f"NEPLATNY model {init.get('model')} místo {model}"); sys.exit()
if init.get('claude_code_version') != verze: print(f"NEPLATNY Claude Code {init.get('claude_code_version')} místo {verze}"); sys.exit()
if kod == 124: print('OK timeout 1800 s (platný výsledek, zapsat)'); sys.exit()
if res is None: print(f'NEPLATNY chybí řádek result (exit {kod})'); sys.exit()
if res.get('is_error') or res.get('subtype') != 'success':
    print('NEPLATNY ' + str(res.get('subtype')) + ': ' + str(res.get('result') or '').replace('\n', ' ')[:200]); sys.exit()
print(f'OK exit {kod}')
PY
}

# výsledek fáze proti výchozímu stavu varianty, stejně jako beh.sh / pokracovani.sh
vysledek() {  # $1 přípona (-1 nebo prázdná), $2 zpráva commitu, $3 relace
  git status --short > "$OUT/zmeny$1.txt"
  git add -A
  git -c user.name="Agent (záznam)" -c user.email="agent@example.invalid" commit -q -m "$2" || true
  git diff "$TAG" HEAD > "$OUT/diff$1.patch" || true
  if [ -z "$1" ]; then git diff --stat "$TAG" HEAD > "$OUT/zmeny-celkem.txt" || true; fi
  local KC=0; timeout 900 make check > "$OUT/make-check$1.txt" 2>&1 || KC=$?
  echo "make check exit=$KC" >> "$OUT/make-check$1.txt"
  grep -rn "App\\\\Inventory\\\\Domain" src/Ordering > "$OUT/hranice$1.txt" 2>/dev/null || true
  echo "nalezeno=$(wc -l < "$OUT/hranice$1.txt")" >> "$OUT/hranice$1.txt"
  python3 "$DIR/prubeh.py" "$OUT/$3" "$OUT/PRUBEH$1.md" > /dev/null || true
  if [ -e "$OBAL/senzory.log" ]; then mv "$OBAL/senzory.log" "$OUT/senzory$1.log"; else : > "$OUT/senzory$1.log"; fi
}

cd "$APP"

# první fáze: zadání
date -Is > "$OUT/cas-1.txt"
set +e
PATH="$OBAL:$PATH" DISABLE_AUTOUPDATER=1 timeout 1800 "$CLAUDE" -p \
  --output-format stream-json --verbose \
  --permission-mode acceptEdits \
  --allowedTools "$NASTROJE" \
  --model "$MODEL" < "$ZADANI" > "$OUT/relace.jsonl" 2> "$OUT/stderr-1.txt"
K1=$?
set -e
date -Is >> "$OUT/cas-1.txt"; echo "exit=$K1" >> "$OUT/cas-1.txt"
P1="$(platnost "$OUT/relace.jsonl" "$K1")"
echo "faze1=$P1" >> "$OUT/beh.txt"
vysledek -1 "Výsledek běhu agenta" relace.jsonl
if [ "${P1%% *}" != OK ]; then
  echo "NEPLATNY 1. fáze: ${P1#* }" > "$OUT/STAV"
  echo "$ID: NEPLATNY 1. fáze: ${P1#* }"; exit 3
fi

# druhá fáze: stejná zpráva zadavatele do stejné relace
RELACE="$(python3 -c "import json,sys
for l in open(sys.argv[1],encoding='utf-8'):
    try: e=json.loads(l)
    except Exception: continue
    if e.get('session_id'): print(e['session_id']); break" "$OUT/relace.jsonl")"
cp "$ZPRAVA" "$OUT/zprava-2.txt"
date -Is > "$OUT/cas.txt"
set +e
PATH="$OBAL:$PATH" DISABLE_AUTOUPDATER=1 timeout 1800 "$CLAUDE" -p --resume "$RELACE" \
  --output-format stream-json --verbose \
  --permission-mode acceptEdits \
  --allowedTools "$NASTROJE" \
  --model "$MODEL" < "$ZPRAVA" > "$OUT/relace-2.jsonl" 2> "$OUT/stderr.txt"
K2=$?
set -e
date -Is >> "$OUT/cas.txt"; echo "exit=$K2" >> "$OUT/cas.txt"
P2="$(platnost "$OUT/relace-2.jsonl" "$K2")"
echo "faze2=$P2" >> "$OUT/beh.txt"
vysledek "" "Výsledek běhu agenta po druhé zprávě" relace-2.jsonl
if [ "${P2%% *}" != OK ]; then
  echo "NEPLATNY 2. fáze: ${P2#* }" > "$OUT/STAV"
  echo "$ID: NEPLATNY 2. fáze: ${P2#* }"; exit 3
fi
echo HOTOVO > "$OUT/STAV"
echo "$ID: HOTOVO (varianta $V, $MODEL), změněných souborů celkem $(grep -c . "$OUT/zmeny-celkem.txt" || true), kopie v $APP"
