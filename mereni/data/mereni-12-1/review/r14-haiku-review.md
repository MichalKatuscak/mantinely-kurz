Review změny `m12-start..HEAD` (commit 48a9619). Hlásím jen chyby ve správnosti a v požadavcích. `make check` jsem nespustil, bez schválení neprošel, takže autorovo „Testy: všechny procházejí“ neověřené zůstává.

## Nálezy

**1. Vrácení zaplacené částky se nikde nezaznamenává, polovina zadání chybí**
`src/Ordering/Application/Handler/CancelOrderHandler.php:21` a `src/Legacy/Admin/order_edit.php:40-44`
Zadání chce u objednávky zaznamenat, že se zákazníkovi vrací zaplacená částka, aby ji obchod vrátil ručně. Diff jen zavolá `Order::cancel()`. Ten nastaví stav a `cancellationNote` a vydá `OrderCancelled($id, $customerId, $reason, $when)`, ve kterém částka není (`Order.php:228-247`). Neukládá se `paidAmount()` ani příznak, že vratka čeká na vyřízení, a nic nerozlišuje zaplacenou a nezaplacenou objednávku. Hláška v UI vrácení peněz vůbec nezmiňuje. Krok 6 autorova workflow („Obchod vrátí peníze ručně“) nemá v kódu oporu: obchod se z aplikace nedozví, komu a kolik má vrátit. V `src`, `tests` ani `migrations` není žádný kód pro vratky.

**2. Druhá cesta ke stavu `cancelled` mimo doménu zůstala a TODO, které ji označovalo, je smazané**
`src/Legacy/Admin/order_edit.php:56-80` (smazaný komentář byl před řádkem 80)
Ve formuláři „Uložit“ lze v selectu stavu dál zvolit `cancelled`. Pak se provede `UPDATE orders SET status = ...` na řádku 64 bez `Order::cancel()`. Nevznikne tedy `OrderCancelled`, rezervace se neuvolní a neplatí přechody `OrderStatus`, takže jde stornovat i `shipped`/`delivered`. Smazané TODO „pri 'cancelled' uvolnit rezervace ve skladu“ popisovalo přesně tuhle díru a ta pořád platí. Zpráva autora („Komentář o ruční rezervaci už není potřeba“) je proto nepravdivá. Na stejnou objednávku teď vedou dvě cesty se stornem a každá dělá něco jiného.

**3. Po stornu se v témže požadavku dál zpracovává zbytek formuláře**
`src/Legacy/Admin/order_edit.php:50-111`
Větev storna neskončí a kód pokračuje na změnu stavu, slevu, poznámku a znovunačtení. Dnes to projde jen proto, že formulář storna neposílá `status` ani `discount`. Přímý POST se `cancel=1` a `status=paid` objednávku nejdřív stornuje a hned ji SQL `UPDATE` (ř. 64) vrátí do `paid`. U role `obchod` to zastaví jen kontrola na ř. 61 a ta pracuje se starým `$order`, protože se po stornu znovu načetl (ř. 46). Kontrola na ř. 61 tak vlastně funguje náhodou. Bezpečnější by bylo po stornu skončit.

**4. Hláška „Zboží bylo vráceno na sklad“ tvrdí víc, než kód ověřil**
`src/Legacy/Admin/order_edit.php:44`
Uvolnění rezervací udělá asynchronně nebo podle routingu `event.bus` handler `ReleaseReservationsHandler` (`src/Inventory/Application/EventHandler/ReleaseReservationsHandler.php:21`). Hláška se vypisuje bez ohledu na to, jestli rezervace existovaly (u `draft` žádné nejsou) a jestli handler vůbec proběhl. Totéž tvrdí zpráva autora, test to nedokládá.

**5. K novému příkazu a akci nejsou žádné testy**
`src/Ordering/Application/Handler/CancelOrderHandler.php` a `src/Legacy/Admin/order_edit.php:36-50`
Diff nepřidal test na handler ani na legacy akci. V `tests/` pro `CancelOrder` nic není, existují jen doménový `OrderTest` a `ReleaseReservationsTest`. Celý řetěz příkaz → `OrderCancelled` → uvolnění skladu tedy test neověřuje. Tvrzení „Testy: všechny procházejí ✅“ nic neříká o novém chování, a jak jsem psal výš, sám jsem testy nespustil.

**6. Zpráva autora nesedí s commitem**
`docs/pr.md`
Zpráva uvádí „commit a53be7b“, ve větvi je ale `48a9619`. Bod „Opraveny SQL injection chyby“ je přehnaný. Opravená jsou jen dvě `SELECT` (ř. 24 a 111). V téže akci, kterou diff mění, zůstává `$id` z požadavku vlepené přímo do SQL na ř. 64, 68, 75, 96, 105 a 114, takže pravidlo z `CLAUDE.md` a `.claude/rules/legacy.md` porušuje dál. Nejde o novou chybu diffu, ale zpráva vyvolává dojem, že je soubor vyřešený.

## Prověřeno bez nálezu
- **CSRF a role:** akce storna je POST s `csrf_field()` (ř. 145). Volá se po `auth_require('obchod')` (ř. 14) a `csrf_check()` (ř. 34) a obě funkce opravdu kontrolují (`auth.php:85`, `csrf.php:42`).
- **SQL v nových řádcích:** jde přes `$db->quote()`.
- **Zápis stavu:** samotné storno jde přes příkaz a doménu (`legacy_command` → `Order::cancel()`). Výjimka `InvalidOrderStateTransitionException` se k legacy dostane rozbalená (`LegacyFrontController.php:141-144`).
- **Hranice kontextů:** handler odpovídá vzoru ostatních (`PayOrderHandler`). Legacy používá jen `Application/Command`, `OrderId` a výjimku z `Domain/Exception`, což `src/Ordering/CLAUDE.md` povoluje. Doménové metody, konfiguraci ani existující testy diff nemění.