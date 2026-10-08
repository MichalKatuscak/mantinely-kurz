# Review: storno ve staré administraci (`m12-start..HEAD`, commit 1bfdf54)

Diff stavu objednávky ani skladu nemění mimo doménu. Akce `order_cancel` volá příkaz `CancelOrder`, ten volá `Order::cancel()`, ta vyvolá `OrderCancelled` a na něj reaguje `ReleaseReservationsHandler`. Hodnota do SQL jde přes `$db->quote()` (`src/Legacy/Admin/OrderController.php:177`). Kontrola role (`auth_require('obchod')`) i CSRF (`csrf_check()` porovná token přes `hash_equals`) opravdu kontrolují a proběhnou dřív než zápis (`OrderController.php:171–174`). Formulář má `csrf_field()` (`src/Legacy/templates/orders/detail.php:68`).

Závažné jsou dvě věci: zpráva autora neodpovídá diffu (nález 1) a změna chování doménové metody bez potvrzení (nález 2).

## Nálezy

**1. Zpráva autora neodpovídá diffu** (`docs/pr.md`)
- **„Změny jsou nepotvrzené v pracovním stromu“:** to neplatí. Všechno je commitnuté v `1bfdf54` a pracovní strom je čistý, kromě `docs/pr.md`.
- **„Dřívější commit `c761cef` už obsahuje první část storna“:** takový commit v repozitáři není (`git log --all` ukáže jen `4f6623d` a `1bfdf54`). Command, handler, akce, tlačítko a test, které autor přisuzuje cizímu commitu, jsou součástí tohoto diffu:
  - `src/Ordering/Application/Command/CancelOrder.php`
  - `src/Ordering/Application/Handler/CancelOrderHandler.php`
  - `src/Legacy/Admin/OrderController.php:161–199`
  - `src/Legacy/Http/LegacyFrontController.php:112`
  - `src/Legacy/templates/orders/detail.php:67–72`
  - `tests/Ordering/Application/CancelOrderHandlerTest.php`
- **Neúplný seznam toho, co je nové:** zpráva tyto části vynechává a jako nové testy uvádí jen `OrderRefundTest.php`. Kdo by se řídil zprávou, polovinu změny by nezkontroloval.

**2. Změna chování doménové metody `Order::cancel()`** (`src/Ordering/Domain/Model/Order.php:249–252`, nové pole na řádku 69)
- `cancel()` nově zapisuje `refundDueInCents`. `CLAUDE.md` takovou změnu bez výslovného zadání zakazuje, a když úkol bez ní nejde, má se autor „zastavit a říct to“.
- Zadání chce vrácení peněz „u objednávky zaznamenat“, takže změna je obhajitelná. Rozhodnutí měnit doménovou metodu ale padlo bez potvrzení. Autor uvádí jen „signatury jsem nezměnil“, ne to, že mění chování.

**3. Storno má vedle nové cesty dál staré cesty, které peníze ani zboží nevrátí**
Tyto cesty mění stav přímo SQL, bez `Order::cancel()`:
- `src/Legacy/Admin/orders.php:30`: hromadné storno, a to i zaplacených a odeslaných objednávek
- `src/Legacy/Admin/order_edit.php:44`: změna stavu na `cancelled`
- `src/Legacy/cron.php:33`

Zaplacená objednávka stornovaná tudy nedostane `refund_due_in_cents` a rezervace zůstanou viset. Diff přidal třetí cestu ke stavu `cancelled` a staré nechal beze změny. Požadavek „zákazník dostane zpět peníze a zboží se vrátí na sklad“ tedy platí jen pro nové tlačítko v detailu. Existující cesty zadání výslovně nezmiňuje, takže to není regrese. Měl by to ale říct buď autor, nebo zadavatel.

**4. Vrácení zboží u zaplacené objednávky žádný test neověřuje** (`tests/Ordering/Application/CancelOrderHandlerTest.php:37–52`)
- `cancelsOrderAndReturnsStock` stornuje jen rozpracovanou (`draft`) objednávku.
- Pro zaplacenou objednávku žádný test nepotvrzuje, že se zboží vrátí na sklad (podle kódu by mělo, rezervace platí až do storna). Netestuje se ani to, že se `refund_due_in_cents` uloží do databáze a znovu načte. Autor to sám přiznává.

**5. Částka k vrácení nemá doménové pravidlo pro změnu** (`src/Ordering/Domain/Model/Order.php:69`)
- Hodnotu umí nastavit jen `cancel()`, nic ji nemění ani nenuluje.
- Když admin vrátí stornovanou objednávku do jiného stavu přes `order_edit.php:41–44`, sloupec zůstane vyplněný. Detail (`detail.php:57–59`) pak u aktivní objednávky ukáže „K vrácení zákazníkovi“.
- Jde o hraniční případ, který vzniká jen ve spojení se starou cestou přes SQL.

## Duplicitní výpočet
Nenašel jsem žádný. Částka k vrácení se bere z existující `Order::paidAmount()` a ve staré administraci se žádná obdobná částka nepočítá (`InvoiceHelper::creditNote()` je jen neimplementovaný TODO).

## Co jsem neověřil
Spustit `make check` mi prostředí nepovolilo. Tvrzení autora o 39 a 120 testech, PHPStanu a Deptracu jsem proto neověřil, stejně jako to, že migrace vznikla generátorem. Podle formy (SQLite `ADD COLUMN` a down migrace přes dočasnou tabulku) to tak vypadá.