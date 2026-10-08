model: claude-opus-5-5  nástroj: Claude Code 2.1.291
[1] Skill: {"skill": "novy-pripad-uziti", "args": "Ve staré administraci (src/Legacy) přidat u objednávky storno: zákazník dostane zpět zaplacenou částku a zboží se vrátí na sklad."}
   → Launching skill: novy-pripad-uziti
[2] Bash: git ls-files | grep -v '^vendor' | head -200 && cat Makefile
   → .claude/hooks/existujici-testy.php .claude/rules/legacy.md .claude/settings.json .claude/skills/novy-pripad-uziti/SKILL.md .codex/hooks.json .cursor/hooks.json .cursor/hooks/check-changed.php .editorconfig .env .env.dev 
[3] Bash: cat src/Ordering/CLAUDE.md .claude/rules/legacy.md src/Ordering/Domain/Model/Order.php src/Ordering/Domain/ValueObject/OrderStatus.php src/Ordering/Domain/Event/OrderCancelled.php src/Inventory/Applic
   → # Ordering  Slovník: objednávka (`Order`), položka (`OrderItem`), storno (`cancel()`, `OrderCancelled`). Pozor: rezervace ≠ storno. Rezervace zboží patří Inventory, ne Orderingu.  Veřejné pro ostatní kontexty: události v
[4] Bash: grep -rni "payment\|PAYMENT_API\|vrácen\|refund" src config tests docs README.md | head -30; ls src/SharedKernel -R 2>/dev/null | head; ls tests -R | head -60; cat src/Ordering/Application/Command/Shi
   → config/secrets/dev/dev.PAYMENT_API_KEY.ff6c0b.php:1:<?php // dev.PAYMENT_API_KEY.ff6c0b on Mon, 05 Oct 2026 11:52:44 +0000 config/secrets/dev/dev.list.php:4:    'PAYMENT_API_KEY' => null, tests/Ordering/Domain/OrderTest.
[5] Bash: cat src/Legacy/Admin/OrderController.php; sed -n 180,230p src/Legacy/lib/functions.php; cat src/Legacy/templates/orders/detail.php; cat docs/plany/zmena-mnozstvi.md
   → <?php /**  * Objednavky – seznam a detail.  *  * Editace stavu a slevy je v order_edit.php, hromadne storno v orders.php  * (stare stranky, nikdo je neprepsal).  */  namespace App\Legacy\Admin;  class OrderController ext
TEXT: Zatím jsem nic neměnil. Prošel jsem doménu a starou administraci a jedna část zadání nejde udělat bez vašeho rozhodnutí.  ## Co už existuje - **Storno v doméně:** `Order::cancel()` (`src/Ordering/Domain/Model/Order.php:229`) hlídá přechody stavů a zaznamená `OrderCancelled`. - **Vrácení zboží na sklad:** `ReleaseReservationsHandler` (`src/Inventory/Application/EventHandler/ReleaseReservationsHandl

VÝSLEDEK (7 kroků, 30 s):
Zatím jsem nic neměnil. Prošel jsem doménu a starou administraci a jedna část zadání nejde udělat bez vašeho rozhodnutí.

## Co už existuje
- **Storno v doméně:** `Order::cancel()` (`src/Ordering/Domain/Model/Order.php:229`) hlídá přechody stavů a zaznamená `OrderCancelled`.
- **Vrácení zboží na sklad:** `ReleaseReservationsHandler` (`src/Inventory/Application/EventHandler/ReleaseReservationsHandler.php`) na `OrderCancelled` uvolní rezervace. Rezervace nesnižuje `onHand`, takže po uvolnění je zboží znovu k dispozici.
- **Chybí:** příkaz `CancelOrder` s handlerem a akce ve staré administraci. To je běžný postup podle skillu `novy-pripad-uziti` a vzoru `changeItemQuantityAction()`.

## Co brání: vrácení zaplacené částky
V kódu není nic, co by peníze vracelo. Chybí napojení na platební bránu (je jen secret `PAYMENT_API_KEY`, který nic nepoužívá), událost ani sloupec pro vrácenou částku, a `OrderCancelled` částku nenese. Udělat vrácení „skutečně“ by znamenalo vymyslet platební integraci nebo nový doménový pojem, případně přidat balíček. To podle pravidel projektu bez zadání nesmím.

**Doporučuji:** storno přes doménu (`CancelOrder` → `Order::cancel()` → `OrderCancelled` → uvolnění rezervací). U zaplacené objednávky by stará administrace po stornu jen ukázala částku k vrácení z `Order::paidAmount()` a samotné vrácení peněz by zatím šlo ručně, mimo aplikaci. Skutečné vracení přes bránu by byl samostatný tiket.

## Další věci k potvrzení
1. **Odeslané a doručené objednávky:** doména je stornovat nedovolí (`OrderStatus::Shipped`, jen přechod na `Delivered`). Počítám s tím, že u nich stará administrace ukáže chybu a vratka zboží od zákazníka je mimo rozsah. Souhlasíte?
2. **Role:** kdo smí stornovat? Navrhuji `obchod` (a `admin`), stejně jako u změny množství.
3. **Dopad na report:** storno zaplacené objednávky ji zpětně vyřadí z měsíčního reportu tržeb, protože report filtruje podle aktuálního stavu (`docs/legacy-mapa.md`). Takhle se report chová už teď, jen to tím bude častější.

Jakmile potvrdíte doporučený rozsah (nebo řeknete, jak má vrácení peněz fungovat), udělám plán a půjdu po krocích s commitem a `make check` po každém z nich.