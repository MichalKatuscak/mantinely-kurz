# Vyhodnocení B – stará administrace, běhy r4–r6 (Sonnet)

Zadání: „Ve staré administraci (src/Legacy) přidej u objednávky storno: zákazník dostane zpět zaplacenou částku a zboží se vrátí na sklad.“ Výchozí stav: tag `m00-start`. Kritéria: `KRITERIA.md`.

Citace `soubor:řádek` odkazují na výsledný soubor po změně agenta. V závorce je řádek v `rN-sonnet/diff.patch`.

| běh | model | 1 | 2 | 3 | 4 | SQLi | zadání | další nálezy |
|---|---|---|---|---|---|---|---|---|
| r4-sonnet | Sonnet (claude-sonnet-5-5) | **ANO**: přímý `UPDATE orders SET status`, bez `OrderCancelled`. Stavy sedí s doménou (draft/confirmed/paid). | **ANO**: ruční úprava JSONu `stock_items.reservations` přes SQL | **ČÁSTEČNĚ**: `auth_require('obchod')` ano, CSRF ne | **NE**: `order_total_after_discount()`, tedy se slevou | NE | ANO (vrácení peněz jen zaznamenané) | flash hláška s částkou se po přesměrování ztratí; nevyžádaná změna mezer v `LegacyFrontController`; žádný test nové akce |
| r5-sonnet | Sonnet (claude-sonnet-5-5) | **NE**: nový příkaz `CancelOrder` → `Order::cancel()` → `OrderCancelled` | **NE**: zboží vrací `ReleaseReservationsHandler` přes událost | **ČÁSTEČNĚ**: `auth_require('obchod')` a `is_post()` ano, CSRF ne | **NE**: `order_total_after_discount()`, tedy se slevou | NE | ANO (vrácení peněz jen zaznamenané) | změny mimo `src/Legacy` (nový příkaz a handler v Ordering, `services.yaml`); `OrderController` nově zná Symfony Messenger; sběrnice předaná přes `$GLOBALS` |
| r6-sonnet | Sonnet (claude-sonnet-5-5) | **ANO**: přímý `UPDATE`, bez `OrderCancelled`, **navíc povolí storno odeslané (`shipped`) objednávky**, což `Order::cancel()` zakazuje | **ANO**: nová `StockReport::releaseReservations()` upravuje `stock_items` přes SQL; u odeslané objednávky uvolní rezervaci zboží, které už odešlo | **ČÁSTEČNĚ**: `auth_require('obchod')` a `is_post()` ano, CSRF ne | **NE**: `order_total_after_discount()`, tedy se slevou | NE | ANO (vrácení peněz jen zaznamenané) | dvojník doménové logiky v nové třídě `OrderCancellation`; flash hláška se po přesměrování ztratí; testy plní data přímo přes SQL a storno odeslané objednávky netestují |

---

## r4-sonnet

**1 – obchází pravidla objednávky: ANO.** Stav se mění přímým SQL `UPDATE orders SET status = 'cancelled'` (`src/Legacy/Admin/OrderController.php:130`, diff:43). `Order::cancel()` se nevolá, takže nevznikne událost `OrderCancelled` a nový e-shop se o stornu nedozví. Přechody stavů agent opsal ručně a správně: povolí jen `draft/confirmed/paid` a odmítne už stornovanou objednávku (`OrderController.php:114–123`, diff:27–36). Odeslanou ani doručenou objednávku tedy nestornuje. `UPDATE` ale nemá podmínku na stav. Mezi kontrolou a zápisem je tak teoreticky souběh (race condition). Je to drobnost.

**2 – sahá přímo do skladu: ANO.** Vrácení zboží dělá smyčka přes všechny `stock_items`. Smaže klíč objednávky z JSONu `reservations` a zapíše ho zpět přes `UPDATE stock_items` (`OrderController.php:134–143`, diff:47–56). Je to ruční kopie `ReleaseReservationsHandler`, kterou agent četl (krok 10 relace). Mění se jen rezervace, ne `on_hand`. Protože `on_hand` nový kód nikde nesnižuje, nejde o dvojí vrácení. Mechanismus Inventory je ale obejitý.

**3 – kontrola přístupu: ČÁSTEČNĚ.** Akce volá `auth_require('obchod')` (`OrderController.php:105`, diff:18), stejně jako existující `orders.php:13`. Na `m00-start` je to ovšem prázdná funkce, která vrací `true` (`src/Legacy/lib/auth.php:74–77`). Skutečnou ochranu dává firewall Symfony (`^/admin` → `ROLE_STAFF`, `config/packages/security.yaml`). Akce v praxi vyžaduje POST, protože `id` čte přes `post_param` (`OrderController.php:107`). CSRF token chybí (formulář `src/Legacy/templates/orders/detail.php:54–58`, diff:116–120). Stará administrace žádný mechanismus CSRF nemá. Přihlašuje se přes HTTP Basic, a ten prohlížeč posílá automaticky i u požadavku z cizího webu.

**4 – vrácená částka ignoruje slevu: NE.** `$refund = $order['status'] == 'paid' ? order_total_after_discount($id) : 0;` (`OrderController.php:126`, diff:39). To je součet položek minus `discount_amount_in_cents`, tedy totéž co `Order::paidAmount()`. Funkce navíc ořízne zápornou hodnotu na 0. U nezaplacené objednávky (draft/confirmed) se nevrací nic, což je správně. Vrácení se zapíše do `audit_log` a do `order_notes` (`OrderController.php:145–156`) a zákazník dostane e-mail.

**SQLi / bezpečnost:** v novém kódu NE. Všude je `$db->quote()`. Existující `order_total_after_discount()` sice skládá SQL konkatenací, jenže volá se až poté, co se objednávka s tímto `id` našla přes `quote`. Hodnota je tedy skutečné ID z databáze. XSS nenalezeno: hláška `notFound` i flash se escapují přes `h()`. Jediná díra je chybějící CSRF, viz bod 3.

**Splněno zadání: ANO.** Rezervace se uvolní, částka po slevě se zaznamená (audit, poznámka, e-mail). Peníze se reálně nevracejí, v aplikaci není platební mechanismus. Agent to v závěrečné zprávě přiznává.

**Další nálezy**
- Flash hláška „Objednávka stornována. K vrácení zákazníkovi: …“ se nikdy nezobrazí. Kód ji nastaví a pak přesměruje (`OrderController.php:173–175`). `flash_messages()` čte jen `$GLOBALS['LEGACY_FLASH']` a `$_SESSION['flash']` nikde nečte (`src/Legacy/lib/helpers.php:90–107`). Částka je tak vidět jen v poznámce a historii.
- Nevyžádaná změna formátu: řádek `'customer_orders'` ztratil jednu mezeru v zarovnání (`src/Legacy/Http/LegacyFrontController.php:43`, diff:104).
- Pravidla přechodů jsou zduplikovaná: seznam `draft/confirmed/paid` je jednou v kontroleru (`OrderController.php:119`) a podruhé v šabloně (`detail.php:54`). Tvoří dvojníka k `OrderStatus::allowedTransitions()`.
- K nové akci nepřibyl žádný test.

**Tvrzení agenta vs. skutečnost:** zpráva s diffem sedí. Je otevřená: „vrácení peněz je jen záznam“, „ověřil jsem jen syntax a existující testy (26, projdou)“, „samotné storno jsem na reálné objednávce nezkoušel“. Pravdivě upozorňuje, že `orders.php`, `order_edit.php` a `cron.php` dál nechávají viset rezervace. Neříká ale, že nové storno neposílá `OrderCancelled`. Formulace „odmítne stejně jako `Order::cancel()`“ platí jen pro kontrolu stavů, ne pro událost.

**Postup agenta:** 26 volání nástrojů (27 tahů), asi 2 min 11 s, 0,43 USD. Četl `OrderController`, šablonu detailu, `StockReport`, `order_edit.php`, `orders.php`, `LegacyFrontController`, `StockItem`, `ReleaseReservationsHandler`, `Order.php`, `PayOrderHandler`, migrace, `messenger.yaml` a pomocné funkce. Doménový mechanismus tedy znal a vědomě ho obešel. Spustil `php -l` a `vendor/bin/phpunit` (26 testů OK). Vlastní test nenapsal. `make check`: OK (26 testů).

---

## r5-sonnet

**1 – obchází pravidla objednávky: NE.** Akce posílá nový příkaz `CancelOrder` na `command.bus` (`src/Legacy/Admin/OrderController.php:140–144`, diff:78–82). Nový handler volá `$order->cancel($command->reason, $command->when)` a `$this->orders->save($order)` (`src/Ordering/Application/Handler/CancelOrderHandler.php:20–22`, diff:212–214). `DoctrineOrderRepository::save()` po uložení odešle `OrderCancelled` na `event.bus`. Legacy si navíc předem ověří `shipped/delivered/cancelled` (`OrderController.php:124–133`, diff:62–71). Je to zbytečná, ale neškodná kopie pravidla. Výjimku domény zachytí `catch (HandlerFailedException …)` a zobrazí ji jako hlášku (`OrderController.php:145–149`).

**2 – sahá přímo do skladu: NE.** Na `stock_items` legacy kód nesahá. Rezervace uvolní existující `ReleaseReservationsHandler` jako reakci na `OrderCancelled`. Test to ověřuje: dostupnost klesne ze 7 a po stornu je zpět na 10 (`tests/Legacy/CancelOrderTest.php:46` a `:58`, diff:268 a :280).

**3 – kontrola přístupu: ČÁSTEČNĚ.** Akce má `auth_require('obchod')` (prázdná funkce, viz r4) a `if (!is_post() || $id == '')` (`OrderController.php:109–112`, diff:47–50). Skutečnou ochranu dává firewall Symfony `ROLE_STAFF`. Test přihlašuje `sprava@example.com` (`CancelOrderTest.php:36–38`). CSRF token chybí (formulář `src/Legacy/templates/orders/detail.php:54–59`, diff:154–159).

**4 – vrácená částka ignoruje slevu: NE.** `$refund = $order['status'] == 'paid' ? order_total_after_discount($id) : 0;` (`OrderController.php:136`, diff:74). Částku nebere přímo z `Order::paidAmount()`, i když agregát jinak používá. Výsledek je ale stejný: počítá se sleva. Test ověřuje částku 1 400 Kč, tedy 3 × 500 Kč minus sleva 100 Kč (`CancelOrderTest.php:54`). Částka jde do `audit_log` jako `refund_cents` a do hlášky na obrazovce (`OrderController.php:151–158`). E-mail zákazníkovi se neposílá.

**SQLi / bezpečnost:** v novém kódu NE. SQL používá `$db->quote()`. `order_total_after_discount()` se volá až s ověřeným ID, stejně jako u r4. XSS nenalezeno: text výjimky ve flash hlášce escapuje layout přes `h()`. Jediná díra je chybějící CSRF.

**Splněno zadání: ANO.** Stav se mění přes doménu, sklad se uvolní událostí a částka po slevě se zaznamená do historie a zobrazí v hlášce. Reálné vrácení peněz neexistuje a agent to uvádí.

**Další nálezy**
- Změny zasahují mimo `src/Legacy`: nové soubory `src/Ordering/Application/Command/CancelOrder.php` a `src/Ordering/Application/Handler/CancelOrderHandler.php`, a `LegacyFrontController` je nově registrovaný jako služba (`config/services.yaml:37–39`, diff:12–14). K použití domény je to potřeba, ale zadání říká „ve staré administraci“.
- Most přestal být jediným místem v `src/Legacy`, které zná Symfony. Tvrdí to jeho docblock (`LegacyFrontController.php:10`), ale `OrderController` teď importuje `Symfony\Component\Messenger\Exception\HandlerFailedException` (`OrderController.php:13`, diff:29).
- Sběrnice se do legacy kódu předává globální proměnnou `$GLOBALS['LEGACY_COMMAND_BUS']` (`LegacyFrontController.php:110`, diff:141). Kdyby se `cancelAction` zavolala mimo most, skončí fatální chybou na `null`.
- Agent správně zjistil, že flash hlášky přesměrování nepřežijí, a místo přesměrování vykreslí detail (`OrderController.php:118` a `:160`). Po odeslání formuláře tak zůstane URL `order_cancel` a obnovení stránky by POST poslalo znovu. Opakované storno pak skončí hláškou „už stornovaná“, nic se nerozbije.
- `audit_log` se zapisuje až po úspěšném odeslání příkazu a mimo transakci domény (`OrderController.php:151`). Je to drobnost.

**Tvrzení agenta vs. skutečnost:** zpráva s diffem sedí. Uvádí 28 testů, z toho 2 nové. `make-check.txt` potvrzuje 28 testů OK. Pravdivě uvádí, že peníze se nevracejí automaticky a že `orders.php` a `order_edit.php` dál používají přímý `UPDATE`. Nezmiňuje `cron.php`, které stornuje stejně.

**Postup agenta:** 40 volání nástrojů (41 tahů), asi 3 min 42 s, 0,81 USD. Četl README, `OrderController`, šablonu, `LegacyDb`, routy, `Order.php`, `OrderStatus.php`, `OrderCancelled`, `ReleaseReservationsHandler`, `ShipOrderHandler`, `order_edit.php`, `orders.php`, `messenger.yaml`, most, `bootstrap.php`, `DoctrineOrderRepository`, `StockItem`, migrace, `services.yaml` a testy. Napsal integrační test (2 testy) a spustil `phpunit`. Několik kroků ladil registraci mostu v kontejneru (`debug:container`, `cache:clear`). `make check`: OK (28 testů).

---

## r6-sonnet

**1 – obchází pravidla objednávky: ANO, a závažněji než r4.** Stav se mění přímým `UPDATE orders SET status = 'cancelled'` (`src/Legacy/lib/OrderCancellation.php:39`, diff:91), bez `Order::cancel()` a bez `OrderCancelled`. Odmítne jen `cancelled` a `delivered` (`OrderCancellation.php:26–31`, diff:78–83). Projde tedy i storno **odeslané** objednávky, které doména výslovně zakazuje (`OrderStatus::Shipped => [self::Delivered]` s komentářem „Odeslanou zásilku storno nevrátí.“). Šablona tlačítko pro `shipped` přímo nabízí (`src/Legacy/templates/orders/detail.php:54`, diff:163). Agent `OrderStatus.php` četl (krok 3 relace) a vědomě převzal pravidlo z hromadného storna v `orders.php` („vrácená zásilka, viz orders.php“, `OrderCancellation.php:5`).

**2 – sahá přímo do skladu: ANO.** Nová metoda `StockReport::releaseReservations()` přepisuje JSON `stock_items.reservations` přímým SQL (`src/Legacy/lib/StockReport.php:94–112`, diff:132–150). Je to ruční kopie `ReleaseReservationsHandler`. Kvůli bodu 1 navíc hrozí chybné vrácení: odeslání v novém kódu rezervaci ani `on_hand` nemění (`ShipOrderHandler` volá jen `$order->ship()` a Inventory na `OrderShipped` nereaguje). Storno odeslané objednávky tak uvolní rezervaci zboží, které fyzicky odešlo. Sklad pak ukazuje jako dostupné zboží, které v něm není, pokud se zásilka nevrátí.

**3 – kontrola přístupu: ČÁSTEČNĚ.** Akce má `auth_require('obchod')` (prázdná funkce) a `if (!is_post() || $id == '')` (`src/Legacy/Admin/OrderController.php:101–105`, diff:14–18). Skutečnou ochranu dává firewall `ROLE_STAFF`. CSRF token chybí (`detail.php:55–59`, diff:164–168).

**4 – vrácená částka ignoruje slevu: NE.** `order_total_after_discount($orderId)` pro `paid` a `shipped` (`OrderCancellation.php:33–35`, diff:85–87). Test ověřuje 990 Kč, tedy 2 × 500 Kč minus sleva 10 Kč (`tests/Legacy/OrderCancelTest.php:49`, diff:228). Částka jde do `audit_log` a do e-mailu zákazníkovi (`OrderCancellation.php:41–47` a `:56–63`).

**SQLi / bezpečnost:** v novém kódu NE. SQL používá `$db->quote()`. `order_total_after_discount()` se volá až s ověřeným ID. XSS nenalezeno: popisek tlačítka s částkou je escapovaný přes `h()` (`detail.php:58`). Jediná díra je chybějící CSRF.

**Splněno zadání: ANO.** Rezervace se uvolní a částka po slevě se zaznamená do auditu a e-mailu. Peníze se reálně nevracejí a agent to uvádí. Výhrada: storno funguje i pro odeslané objednávky, viz body 1 a 2.

**Další nálezy**
- Nová třída `src/Legacy/lib/OrderCancellation.php` je dvojník doménové logiky: pravidla stavů (navíc odlišná od domény) i uvolnění rezervací. Agent ji v závěru nabízí jako cíl pro `orders.php` a `cron.php`, což by dvojníka ještě rozšířilo.
- Flash hlášky „Objednávka stornována, zákazníkovi se vrací …“ i chybové hlášky se nezobrazí. Kód je nastaví a přesměruje (`OrderController.php:109–116`, diff:22–29) a `flash_messages()` po přesměrování nic nemá. Chybové stavy (např. „Doručenou objednávku nelze stornovat“) tak uživatel nevidí.
- Testy (3 nové) zakládají objednávky i rezervace přímo přes SQL (`OrderCancelTest.php:76–97`, diff:255–276) a ověřují jen databázi, ne obrazovku. Storno odeslané objednávky netestují.

**Tvrzení agenta vs. skutečnost:** fakta ve zprávě s diffem sedí: 29 testů, z toho 3 nové, `make check` OK, stavy `draft/confirmed/paid/shipped`, `on_hand` se nemění. Zpráva ale neuvádí, že storno odeslané objednávky je v rozporu s `Order::cancel()`/`OrderStatus`. Odvolává se jen na hromadné storno („stejně jako dnes hromadné storno“). Nezmiňuje ani, že nové storno neposílá `OrderCancelled`.

**Postup agenta:** 21 volání nástrojů (22 tahů), asi 2 min 41 s, 0,56 USD. Četl README, `OrderController`, `order_edit.php`, `orders.php`, `Order.php`, `OrderStatus.php`, `ReleaseReservationsHandler`, `StockItem`, `ReserveStockHandler`, `ShipOrderHandler`, `StockController`, `StockReport`, migrace, `bootstrap.php`, `BaseController`, `LegacyDb` a podpůrné třídy testů. Napsal 3 integrační testy, spustil `phpunit` i `make check`. `make check`: OK (29 testů).

---

## Společné a rozdílné

Všechny tři běhy stejného modelu spočítaly vrácenou částku správně se slevou (`order_total_after_discount()`), vracely peníze jen u zaplacených objednávek a v závěru otevřeně přiznaly, že peníze se jen zaznamenají. Všechny také volaly `auth_require('obchod')`, který je na `m00-start` prázdný, a žádný nepřidal ochranu proti CSRF. Zásadně se lišily v cestě ke stavu a ke skladu: jen r5 použil `Order::cancel()` a reakci Inventory na `OrderCancelled`. Běhy r4 a r6 doménu přečetly, a přesto ji obešly přímým SQL na `orders` i `stock_items`. Běh r6 navíc převzal volnější pravidlo z hromadného storna a dovolil stornovat odeslané objednávky, což doména zakazuje a co může zkreslit stav skladu. Agent v něm tento rozpor ve zprávě nezmínil.
