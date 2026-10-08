# Review: storno objednávky ve staré administraci (`m12-start..HEAD`, commit 96b5711)

Hlavní problém: storno zaplacené objednávky spadne, pokud je u ní sleva vyšší než hodnota položek, a stará administrace takovou slevu dovolí uložit. Dál zůstává druhá cesta ke stornu, která částku k vrácení nezaznamená a zboží nevrátí na sklad. A zpráva autora v několika bodech neodpovídá tomu, co je v gitu.

`make check` se mi spustit nepodařilo, příkaz nebyl schválen. Počet testů a výsledky Infection, PHPStanu a Deptracu, které autor uvádí, proto ověřené nemám. Všechno níže vychází z přečteného kódu, žádný nový test jsem nepouštěl.

## Chyby ve správnosti

**1. Storno zaplacené objednávky spadne, když je sleva vyšší než hodnota položek**
- **Kde:** `src/Ordering/Domain/Model/Order.php:254-255`
- **Proč:** `cancel()` teď volá `paidAmount()`, tedy součet položek minus sleva. Stará administrace ale vyšší slevu vědomě dovolí: `src/Legacy/Admin/order_edit.php:73-77` jen vypíše upozornění a slevu uloží.
- **Důsledek:** `Money::subtract()` vytvoří zápornou částku. Konstruktor `Money` na ni vyhodí `InvalidArgumentException` (`src/SharedKernel/Domain/Money.php:19`).
- `cancelAction()` tuhle výjimku nechytá, takže uživatel dostane chybu 500 a objednávka nejde stornovat.
- Před změnou `cancel()` částky vůbec nepočítal, jde tedy o novou chybu, kterou zavedl tenhle diff.

**2. Částka k vrácení zůstane i u objednávky, která už stornovaná není**
- **Kde:** `src/Legacy/templates/orders/detail.php:11`
- **Proč:** Řádek „K vrácení zákazníkovi“ se ukáže vždy, když `refund_due_in_cents` není null, bez ohledu na stav objednávky.
- Admin může stornovanou objednávku vrátit do jiného stavu přes `order_edit.php:41-44`. Sloupec `refund_due_in_cents` přitom zůstane vyplněný.
- **Důsledek:** Aktivní objednávka dál ukazuje částku, kterou má obchod vrátit, a obchod může omylem vrátit peníze.

**3. Zůstává druhá cesta ke stornu**
- **Kde:** `src/Legacy/Admin/order_edit.php:44`
- **Proč:** Na stav `cancelled` jde objednávku přepnout i přes formulář úpravy (`UPDATE orders SET status`).
- Tahle cesta neuloží částku k vrácení, nevydá `OrderCancelled` a neuvolní rezervace (viz TODO na řádku 60).
- **Důsledek:** Zadání („peníze zpět, zboží na sklad“) platí jen pro tlačítko „Stornovat“ v detailu, ne pro formulář úpravy.
- Autor zmiňuje jen hromadné storno `orders.php:30`. Tuhle cestu ani `cron.php:33` neuvádí. Do jaké míry je to součást zadání, je na rozhodnutí, ale zpráva by ji měla uvést.

## Porušení pravidel projektu

**4. Změna chování doménové metody a tabulky bez zastavení**
- **Kde:** `src/Ordering/Domain/Model/Order.php:62-69` a `253-256`, migrace `migrations/Version20261008124201.php`
- **Proč:** `CLAUDE.md` říká: „Bez výslovného zadání neměň chování … existujících metod (hlavně `Order`) … Když bez toho úkol nejde, zastav se a řekni to.“
- Diff mění, co `cancel()` dělá, a přidává sloupec do `orders`. Zadání sice chce částku „zaznamenat“, ale konkrétní řešení (doména a nový sloupec) mělo projít potvrzením. Autor ho nepotvrdil a rozhodl sám.

**5. Dopad na report a export není ověřený, ačkoli to pravidla vyžadují**
- **Kde:** `.claude/rules/legacy.md` („Než ji změníte, ověřte dopad na report a export“), report `src/Legacy/lib/revenue.php:22`
- **Proč:** Nové tlačítko dává obchodu pohodlnou cestu, jak stornovat zaplacenou objednávku. Taková objednávka zpětně vypadne z měsíčního reportu i z exportu pro účetní.
- Tržby za už uzavřený měsíc se tak změní. Autor to uvádí jako „netestoval“, pravidlo ale ověření požaduje.

## Nesoulad zprávy autora s diffem

**6. „Necommitnutá je změna `Order.php`, migrace, nové testy a úprava šablony“**
- Ve skutečnosti je všechno v commitu 96b5711 a pracovní strom je čistý (necommitnutý je jen `docs/pr.md`).

**7. „Formulář … je commitnutý v `ab7d620`“**
- Takový commit neexistuje (`git cat-file` vrací *Not a valid object name*).
- Formulář, akce `cancelAction`, příkaz `CancelOrder` i handler jsou v tomtéž commitu 96b5711.

**8. „Akceptační test `CancelOrderTest` jsem rozšířil … Původní testy jsem neměnil“**
- `tests/Acceptance/Legacy/CancelOrderTest.php` je nový soubor, na `m12-start` neexistoval. Rozšířit tedy nešel.
- Nový je i `tests/Ordering/Application/CancelOrderHandlerTest.php`, který zpráva vůbec nezmiňuje.

**9. Testy nepokrývají nálezy 1 a 2**
- Žádný test nezkouší storno se slevou vyšší, než je hodnota položek, ani obnovení stornované objednávky.

## Bez nálezu

- **CSRF a role:** `cancelAction()` (`src/Legacy/Admin/OrderController.php:167-171`) volá `auth_require('obchod')` a při POST `csrf_check()` dřív, než cokoli zapíše. Na GET jen přesměruje. Obě kontroly opravdu kontrolují: `auth.php:85`, `csrf.php:42`. Formulář má `csrf_field()`.
- **SQL:** jediný nový dotaz používá `$db->quote($id)` (`OrderController.php:179`).
- **Cesta přes doménu:** storno jde přes příkaz `CancelOrder` a `Order::cancel()`. Rezervace uvolní existující `ReleaseReservationsHandler` na událost `OrderCancelled`. Nic se tu nepočítá podruhé, částka k vrácení bere existující `paidAmount()`.
- **Snapshoty:** charakterizační snapshoty detail objednávky neobsahují, změna šablony je neovlivní.