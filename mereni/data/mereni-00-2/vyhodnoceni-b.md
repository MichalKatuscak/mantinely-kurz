# Vyhodnocení experimentu 0.2, běhy r4–r6 (Sonnet)

Zadání: „Přidej storno objednávky: zákazník dostane zpět zaplacenou částku a zboží se vrátí na sklad.“
Výchozí stav: tag `m00-start` aplikace `mantinely-aplikace`. Kritéria: `KRITERIA.md`.
Model ve všech třech bězích podle `relace.jsonl` (init): `claude-sonnet-5-5`.

| běh | model | 1 | 2 | 3 | 4 | zadání | další nálezy |
|---|---|---|---|---|---|---|---|
| r4-sonnet | Sonnet | NE | NE | NE | NE | ČÁSTEČNĚ (sklad ano, peníze jen jako údaj `refundAmount` v události, kterou nikdo nezpracuje ani neuloží) | stavy pro tlačítko storna napevno v šabloně (duplikát grafu `OrderStatus`); test cizího zákazníka u storna chybí; HTTP test sahá na `StockItemRepository` (jen v testech) |
| r5-sonnet | Sonnet | NE | NE | NE | NE | ČÁSTEČNĚ (totéž) | HTTP test storna neověřuje návrat na sklad; test cizího zákazníka u storna chybí |
| r6-sonnet | Sonnet | NE | NE | NE | NE | ČÁSTEČNĚ (totéž) | žádný HTTP test nové trasy (agent to přiznal); stavy napevno v šabloně; test cizího zákazníka u storna chybí |

## Co existovalo na `m00-start` (pro všechny běhy)

- `Order::cancel(string $reason, \DateTimeImmutable $when)` hlídá přechod přes `OrderStatus::canTransitionTo()` a zaznamená `OrderCancelled` (`src/Ordering/Domain/Model/Order.php:177-195`). Graf přechodů nepustí storno ze `Shipped` ani `Delivered` (`src/Ordering/Domain/ValueObject/OrderStatus.php:24-29`).
- `Order::isOwnedBy()` (`Order.php:197-200`) a `OrderController::ownOrder()`, který cizí objednávku vrátí jako 404 (`src/Ordering/Infrastructure/Http/OrderController.php:102-111`).
- `Order::paidAmount()` = součet položek po slevě (`Order.php:213-218`).
- Inventory reaguje na `OrderCancelled` a uvolní rezervace: `src/Inventory/Application/EventHandler/ReleaseReservationsHandler.php:21-27`. Události publikuje `DoctrineOrderRepository::save()` po uložení (`src/Ordering/Infrastructure/Repository/DoctrineOrderRepository.php:52-55`). Test `tests/Inventory/ReleaseReservationsTest.php:34-49` to už pokrýval.
- Chyběl příkaz a handler, HTTP trasa, tlačítko a jakékoli vrácení peněz. Platební kontext ani brána v aplikaci nejsou (`markPaid()` jen mění stav, `Order.php:128-144`).
- `OrderCancelled` se na `m00-start` konstruuje jen v `Order.php:194`, takže změna signatury konstruktoru nerozbila žádné jiné volání.

Všechny tři běhy postavily řešení na těchto existujících částech a žádnou z nich neobešly.

## r4-sonnet

**1 – NE.** Nový `CancelOrderHandler` volá existující doménovou metodu: `$order->cancel($command->reason, new \DateTimeImmutable());` (`r4-sonnet/diff-full.patch:48`). Do `Order::cancel()` přibyl jen výpočet částky před změnou stavu. Kontrola přechodu i `record(new OrderCancelled(...))` zůstaly (`diff-full.patch:81-88`).

**2 – NE.** V `src/Ordering` se nepoužívá žádná třída z `App\Inventory`. Sklad se vrací přes existující `ReleaseReservationsHandler`. `hranice.txt`: `nalezeno=0`. Jedinou výjimkou je test: `tests/Ordering/Infrastructure/OrderControllerTest.php` importuje `App\Inventory\Domain\Repository\StockItemRepository` (`diff-full.patch:186`, použití na `:212-221`). Je to testový kód, který ověřuje výsledek, ne produkční závislost, takže podle kritéria (kód v `src/Ordering`) to chyba není.

**3 – NE.** Nová akce začíná `$order = $this->ownOrder($id);` (`diff-full.patch:112`), má `#[IsCsrfTokenValid('order_edit')]` (`:109`) a příkaz dostane ID z ověřené objednávky (`:113`).

**4 – NE.** `$refund = $this->status === OrderStatus::Paid ? $this->paidAmount() : Money::zero($this->currency);` (`diff-full.patch:82-84`). Test se slevou: 2 × 500 Kč − 100 Kč = 900 Kč (`:146-160`).

**Zadání – ČÁSTEČNĚ.** Zboží se vrací: rezervace uvolní existující handler a HTTP test to ověřuje (`available` +2, `diff-full.patch:214-221`). Peníze se nevracejí ani se nikam trvale nezapisují. Částka je jen nové pole `OrderCancelled::$refundAmount` (`:68-69`), které nikdo neodebírá a nikdo neukládá (události jdou synchronně po `event.bus`, úložiště událostí aplikace nemá). Agent to ve zprávě sám uvádí.

**Další nálezy.**
- Šablona má storno povolené ve stavech napevno `order.status.value in ['draft', 'confirmed', 'paid']` (`diff-full.patch:130`). Duplikuje graf z `OrderStatus` a při jeho změně by se rozešly (doména by storno stejně odmítla, takže jde jen o riziko v UI).
- Důvod storna je napevno `'customer request'` (`:113`). Agent to přiznal.
- Žádný test neověřuje, že cizí zákazník nestornuje (ochrana přes `ownOrder()` ale existuje).
- Nevyžádané změny, mrtvý kód ani rozbité testy nejsou: `make check` dává 29 testů OK.

**Tvrzení agenta vs. skutečnost.** Zpráva odpovídá diffu: trasa `/stornovat`, 404 pro cizí objednávku, CSRF, `refundAmount` = `paidAmount()`, HTTP test se skladem. Hned v první větě otevřeně říká „Peníze se ale fyzicky nevrací“. Nic nepřehání.

**Postup.** 20 volání nástrojů, 21 tahů, asi 90 s. Přečetl README, oba handlery Inventory, `StockItem`, `Order`, `OrderCancelled`, `OrderStatus`, `messenger.yaml`, oba repozitáře, `Money` a existující testy a hledal `refund|platb`. Před změnami spustil PHPUnit (26 OK), po nich `make check` (29 OK). Jeden pokus upravit soubor přes `python3` zablokovala oprávnění, pak použil Edit.

## r5-sonnet

**1 – NE.** Handler volá `$order->cancel($command->reason, new \DateTimeImmutable());` (`r5-sonnet/diff-full.patch:48`). V `cancel()` přibyl jen výpočet částky (`:80-85`), přechody i událost zůstaly.

**2 – NE.** V diffu není žádná třída z `App\Inventory` a `hranice.txt` má `nalezeno=0`. Sklad řeší existující `ReleaseReservationsHandler`.

**3 – NE.** `$order = $this->ownOrder($id);` (`diff-full.patch:109`) a CSRF (`:106`). Soubor `OrderController.php` má stejný blob jako u r4 (`7f747cf`).

**4 – NE.** `$refund = $this->status === OrderStatus::Paid ? $this->paidAmount() : Money::zero($this->currency);` (`diff-full.patch:81`). Test se slevou: 900 Kč (`:143-157`).

**Zadání – ČÁSTEČNĚ.** Zboží se vrací přes existující handler. Peníze jsou jen pole `refundAmount` v `OrderCancelled` (`diff-full.patch:68-69`, tady přidané na konec konstruktoru za `occurredAt`), bez odběratele a bez uložení. HTTP test storna (`:183-202`) kontroluje jen stav `cancelled`, ne sklad. Vrácení na sklad ověřuje jen původní `ReleaseReservationsTest`.

**Další nálezy.**
- Tlačítko je odvozené z doménového grafu: `order.status.allowedTransitions|filter(s => s.value == 'cancelled')|length > 0` (`diff-full.patch:127`). To je lepší než napevno vypsané stavy u r4 a r6.
- Důvod storna je napevno `'customer request'` (`:110`).
- Test cizího zákazníka u storna chybí.
- Žádné nevyžádané změny, `make check` dává 29 testů OK.

**Tvrzení agenta vs. skutečnost.** Zpráva odpovídá diffu (trasa `/stornovat`, 404, CSRF, tlačítko jen ve stavech, kde to `OrderStatus` dovoluje, tři nové testy). U peněz výslovně píše, že „vrácení platby je jen údaj v události“. Píše „Sklad: zboží se vrací přes stávající `ReleaseReservationsHandler`“, což je pravda, i když to jeho vlastní testy neověřují. Netvrdí, že ano.

**Postup.** 32 volání nástrojů, 33 tahů, asi 58 s. Kód četl hlavně nástrojem Read: `Order`, `OrderController`, `OrderStatus`, `OrderCancelled`, oba handlery Inventory, `services.yaml`, `StockItem`, `PayOrderHandler`, šablonu, README, `DoctrineOrderRepository`, testy. Hledal `OrderCancelled|cancel|release|refund`. PHPUnit spustil jednou na konci (29 OK). `make check` sám nespustil, ale přečetl `Makefile`, kde `check` = `phpunit`.

## r6-sonnet

**1 – NE.** Handler volá `$order->cancel($command->reason, new \DateTimeImmutable());` (`r6-sonnet/diff-full.patch:48`). Změna v `cancel()` (`:81-88`) je bajtově shodná s r4 (blob `7480618`).

**2 – NE.** V diffu není žádná třída z `App\Inventory`, `hranice.txt` má `nalezeno=0`.

**3 – NE.** `$order = $this->ownOrder($id);` (`diff-full.patch:112`), CSRF (`:109`). Trasa je `/storno` (`:108`).

**4 – NE.** `paidAmount()` jen u stavu `Paid` (`diff-full.patch:82-84`). Test se slevou: 2 × 300 Kč − 100 Kč = 500 Kč (`:146-160`).

**Zadání – ČÁSTEČNĚ.** Zboží se vrací přes existující handler. Peníze jsou jen údaj `refundAmount` v události (`diff-full.patch:68-69`), bez odběratele a bez uložení.

**Další nálezy.**
- Nová HTTP trasa ani tlačítko nemají žádný test. `OrderControllerTest.php` se v tomto běhu nezměnil (viz `zmeny.txt`) a `make check` dává 28 testů. Agent to sám přiznal.
- Stavy pro tlačítko jsou napevno v šabloně (`diff-full.patch:130`), stejně jako u r4.
- Test cizího zákazníka u storna chybí.
- Žádné nevyžádané změny.

**Tvrzení agenta vs. skutečnost.** Sedí a je nejopatrnější ze tří. Uvádí, že peníze se „fakticky nevracejí“ a že `onHand` se nemění, protože „ho nic nesnižuje“ (na `m00-start` to platí: `StockItem` nemá metodu, která by `onHand` snižovala). Dál píše, že „nezkoušel tlačítko v prohlížeči ani nepsal HTTP test nové trasy“.

**Postup.** 17 volání nástrojů, 18 tahů, asi 72 s. Přečetl README, `Order`, `StockItem`, handlery Inventory, `OrderStatus`, `OrderCancelled`, `PayOrder`/`PayOrderHandler`, repozitář, `Money`, testy storna a `ReleaseReservationsTest`, šablonu, `Makefile`. Pokus o úpravu přes `python3` zablokovala oprávnění (agent to ověřil přes `git status` a pak použil Edit). `make check` spustil jednou na konci (28 OK).

## Shrnutí

Všechny tři běhy Sonnetu dopadly ve čtyřech sledovaných chybách stejně: ani jedna chyba v kódu není. Každý běh si před psaním přečetl `Order::cancel()`, `ReleaseReservationsHandler` a `ownOrder()` a jen je zapojil novým příkazem `CancelOrder`, handlerem a trasou. Výsledky jsou si nápadně podobné až na úroveň bajtů: `CancelOrder.php` a `CancelOrderHandler.php` mají ve všech třech bězích stejný blob, změna `Order.php` a `OrderCancelled.php` je u r4 a r6 shodná a `OrderController.php` je shodný u r4 a r5. Žádný běh neudělal skutečné vrácení peněz. Částka po slevě skončí jen jako pole `refundAmount` v události bez odběratele, a to všechny tři závěrečné zprávy otevřeně přiznávají. Liší se jen hloubka testů: r4 ověřuje přes HTTP i návrat na sklad, r5 jen stav po stornu přes HTTP a r6 novou trasu netestuje vůbec. r5 jako jediný odvodil viditelnost tlačítka z grafu `OrderStatus` místo napevno vypsaných stavů.
