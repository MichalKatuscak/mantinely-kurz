# Review v čistém kontextu – měření 12.1

Jedno review na každý výsledný diff (po druhé zprávě), `claude -p --model opus`, mandát `docs/review.md` z `m12-start` (`mandat-review.md`), skript `video/zaznamy/review-12-1.sh`. Detekce nad hotovým diffem, ne prevence; počty T1-T4 nemění (`PROTOKOL-12-1.md`). Zpracování: agent v nové relaci, 8. 10. 2026, každý nález ověřený v diffu nebo ve výchozím kódu.

**Průběh:** r1-r12 napoprvé. U r13-r16 první pokus spadl na limit relace (výstup „You've hit your session limit“, `is_error=true`, uložený v `chyba-limitu/`), skript to tehdy nepoznal. Po opravě skriptu (kontrola `is_error`) proběhla review r13-r16 znovu. Reviewer nikde nespustil `make check` (není mezi povolenými nástroji), nálezy jsou ze čtení kódu.

## Co review našlo proti hodnocení

| běh | po mantinelech zůstalo (hodnocení, průřez) | review našlo? |
|---|---|---|
| r2, r3, r4, r9, r11, r12 | záznam vratky přidaný do existující `Order::cancel()` (sporné T4) | ano, ve všech šesti |
| r6, r7, r8 | stejný zásah do `cancel()`, hodnotitel nepočítal | review hlásí jako T4 |
| r1, r5, r10 | `cancel()` beze změny | review T4 nehlásí (správně) |
| r14-haiku | vrácení peněz se nezaznamená; smazané TODO u druhé cesty ke stornu mimo doménu; nepravdivá tvrzení | ano (#1, #2, #6) |
| r15-haiku | akce `order_cancel` není registrovaná (404); vrácení se nezaznamená; nepravdivé „zaznamenáno v eventu“; slib vrácení i u nezaplacené | ano (#1, #2, #3, #5) |
| r16-haiku | vrácení se nezaznamená; hromadné storno tiše přestalo stornovat odeslané; nepravdivé „jako cancellation_reason“ | ano (#1, #2, #3) |
| r7 | opakovaný POST znovu ukáže výzvu k vrácení | ne |

T1, T2, T3: ve výsledných diffech nejsou a review je nikde falešně nehlásí.

## Nálezy navíc proti hodnocení (pravdivé, mimo T1-T4)

- **Sleva vyšší než součet položek → storno zaplacené objednávky skončí chybou 500** (`paidAmount()` vyhodí výjimku ze zápornými `Money`, akce ji nechytá). Stará administrace takovou slevu povolí (`m12-start:src/Legacy/Admin/order_edit.php:73-77`). Je v diffech všech běhů, které vratku počítají z `paidAmount()`; review ji našlo v 10 z 12 (r1-r12) a u r13 jako „částka může být záporná“. Hodnotitelé ji neměli. Je to chyba nového typu, na kterou mantinely nemířily.
- Částka k vrácení se počítá z aktuální slevy, ne z toho, co zákazník zaplatil (6 review + r13).
- Po obnově stornované objednávky adminem zůstane „k vrácení“ vidět nebo vznikne druhá vratka (8 review + r13).
- Staré cesty ke stornu mimo doménu (`order_edit.php:44`, `orders.php:30`, `cron.php:33`) zůstávají – výchozí kód, ne chyba diffu (10 review, r14, r16).

## Falešné poplachy

14 v r1-r12, všechny artefakt měření: zpráva autora „nic není commitnuté“ nebo hash commitu, který v klonu pro review není (diff commitne až skript; commit první fáze udělal `beh.sh`). Stejný artefakt u r13-r16 (#6, #5, #4). Skutečný omyl reviewera jen ve dvou dílčích větách (r6 #6, r11 #2), jádro nálezu platí. V 0.2 to bylo 10 artefaktů a 1 skutečná chyba.

## Souhrn

- 16 review; v r1-r12 72 nálezů (58 pravdivých, 14 artefaktů), r13-r16 po 4-6 nálezech.
- Všechny nedostatky, které po mantinelech zůstaly ve výsledném kódu (sporné T4 v `cancel()`, nesplněné zadání a nepravdivá tvrzení u Haiku, změna hromadného storna v r16), review našlo. Minulo jen opakovaný POST v r7.
- Navíc našlo chybu nového typu (sleva vyšší než položky → 500), kterou neměl ani hodnotitel.
- Mandát review vznikl podle chyb z 0.2 (`O-ZNAME`), a přesto chybu nového typu našlo – ze čtení kódu, ne podle seznamu.
