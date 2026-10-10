#!/usr/bin/env bash
# Kopie aplikace pro ablační doměření 12.1: m12-start a změna právě jedné vrstvy
# (varianty/<V>.patch, popis v PROTOKOL-ABLACE.md).
#
# Použití (WSL): bash priprav-variantu.sh <0|A|B|C> <cílová složka> [--bez-kontroly]
#   0  m12-start beze změny (kontrola skriptu, stejný stav jako 12.1)
#   A  bez navádění (instrukční soubory, pravidlo, skill, mapa, zadání reviewera)
#   B  bez vzoru (stará administrace bez csrf_check()/csrf_field() a auth_require() z modulu 9)
#   C  bez hooků (.claude/settings.json bez sekce hooks)
#
# Kopie vznikne stejně jako v beh.sh měření 12.1: klon jen posledního commitu tagu, bez
# remote, větev beh, composer install, migrace. Změna varianty je vložená do toho jediného
# commitu (stejná zpráva, autor i datum jako m12-start) a tag m12-start ukazuje na něj:
# Makefile (INFECTION_BASE), hook existujici-testy.php i diff výsledku tak počítají od
# výchozího stavu varianty a agent v historii nevidí, co se změnilo.
#
# Ověří, že strom commitu má očekávaný hash a že diff proti původnímu m12-start je
# bajt po bajtu varianty/<V>.patch. Bez --bez-kontroly pustí navíc make check (do
# <cílová složka>/../priprava-make-check.txt, mimo kopii) a ověří, že pracovní strom zůstal čistý.
# Pro skutečné běhy se volá s --bez-kontroly (v 12.1 make check před agentem neběžel,
# a nemá tedy ani tady zahřát cache); shodu s ověřenou variantou zaručuje hash stromu.
#
# Proměnné prostředí: REPO (repozitář s tagem m12-start, výchozí ~/mereni-kit)
set -euo pipefail
V="${1:?varianta 0, A, B nebo C}"; CIL="${2:?cílová složka}"
KONTROLA=1; [ "${3:-}" = "--bez-kontroly" ] && KONTROLA=0
DIR="$(cd "$(dirname "$(readlink -f "$0")")" && pwd)"
REPO="${REPO:-$HOME/mereni-kit}"
TAG=m12-start
PUVODNI=4f6623de83434af2bc1a931de419aa58a97d18c5   # m12-start, výchozí stav 12.1
case "$V" in
  0) STROM=56958311b7eb1c9fe69571a919eb99e4a921bcd9 ;;
  A) STROM=e5a792129983ad9ca46f72e92c02a69291636d49 ;;
  B) STROM=0650b9c3ad466baab07084d031dd711f4ccd51dd ;;
  C) STROM=f9bf6b5e8ba3e3900e8ae4aa84e716810b73acb0 ;;
  *) echo "neznámá varianta: $V (0, A, B, C)" >&2; exit 2 ;;
esac
chyba() { echo "CHYBA ($V): $*" >&2; exit 1; }

[ -e "$CIL" ] && chyba "$CIL už existuje"
mkdir -p "$(dirname "$CIL")"
ZDROJ="$REPO"; [ -d "$REPO" ] && ZDROJ="file://$(cd "$REPO" && pwd)"
git -c advice.detachedHead=false clone -q --depth 1 --no-tags --single-branch --branch "$TAG" "$ZDROJ" "$CIL"
cd "$CIL"
git remote remove origin
[ "$(git rev-parse HEAD)" = "$PUVODNI" ] || chyba "tag $TAG v $REPO není $PUVODNI"

if [ "$V" != 0 ]; then
  git apply --index "$DIR/varianty/$V.patch"
  GIT_COMMITTER_NAME="$(git log -1 --format=%cn)" \
  GIT_COMMITTER_EMAIL="$(git log -1 --format=%ce)" \
  GIT_COMMITTER_DATE="$(git log -1 --format=%cI)" \
    git commit -q --amend --no-edit --no-verify
  # nový commit je stejně jako původní hranicí mělkého klonu (git log ukáže jediný commit)
  git rev-parse HEAD >> "$(git rev-parse --git-dir)/shallow"
fi
git tag -f "$TAG" > /dev/null   # klon s --branch <tag> ho někdy vytvoří sám
git checkout -q -b beh

[ "$(git rev-parse "HEAD^{tree}")" = "$STROM" ] || chyba "strom $(git rev-parse "HEAD^{tree}") místo $STROM"
[ "$(git rev-list --count HEAD)" = 1 ] || chyba "historie má víc než jeden commit"
if [ "$V" != 0 ]; then
  git -c core.quotepath=false diff --no-renames --no-color --no-ext-diff "$PUVODNI" HEAD \
    | cmp -s - "$DIR/varianty/$V.patch" || chyba "diff proti m12-start se liší od varianty/$V.patch"
fi

composer install -q --no-interaction
php bin/console doctrine:migrations:migrate -n -q
[ -z "$(git status --porcelain)" ] || chyba "po přípravě není pracovní strom čistý"

ZMENA="varianty/$V.patch"; [ "$V" = 0 ] && ZMENA="beze změny"
if [ "$KONTROLA" = 1 ]; then
  LOGK="$(dirname "$CIL")/priprava-make-check.txt"
  KC=0; timeout 900 make check > "$LOGK" 2>&1 || KC=$?
  echo "make check exit=$KC" >> "$LOGK"
  [ "$KC" = 0 ] || chyba "make check skončil kódem $KC (výstup v $LOGK)"
  [ -z "$(git status --porcelain)" ] || chyba "make check zanechal změny v pracovním stromu: $(git status --porcelain | head -5)"
  echo "varianta $V: strom $STROM, diff proti m12-start = $ZMENA, make check exit=0 ($LOGK)"
else
  echo "varianta $V: strom $STROM, diff proti m12-start = $ZMENA (make check vynechán)"
fi
