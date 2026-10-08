# Mantinely – ukázková aplikace kurzu

Veřejný repozitář videokurzu **Mantinely: vývoj s AI v PHP a Symfony**
([mantinely.katuscak.cz](https://mantinely.katuscak.cz)). Na této aplikaci se dělají
cvičení a na ní běžel experiment s AI agentem z lekce 0.2. Doménou navazuje na knihu
[DDD v Symfony](https://ddd-v-symfony.katuscak.cz).

Ve složce [`mereni/`](mereni/README.md) je celá sada, se kterou jsem experiment měřil:
zadání, skripty, kritéria, hodnocení a přepisy všech běhů agenta. Měření si můžete
zopakovat se svým modelem.

- PHP 8.4 (`composer.json`: `"php": ">=8.4"`), Symfony 8.1, Doctrine ORM 3, Symfony Messenger, PHPUnit 13.
- Databáze SQLite (`var/data_<prostředí>.db`), žádná služba navíc.
- Ohraničené kontexty `src/Ordering` a `src/Inventory`, sdílené typy v `src/SharedKernel`,
  přihlášení v `src/Identity`, stará administrace v `src/Legacy`.

## Spuštění

```bash
composer install
bin/console doctrine:migrations:migrate -n
symfony server:start        # nebo: php -S localhost:8000 -t public
make check                  # všechny kontroly
```

Ukázkoví uživatelé (heslo `heslo`, HTTP Basic): `alice@example.com` a `bob@example.com`
(zákazníci, `/objednavky`), `sprava@example.com` (stará administrace, `/admin`).

PHP potřebuje rozšíření `pdo_sqlite`, `intl`, `mbstring`, `xml`, `dom`, `xmlwriter`
a pro mutační testy (od modulu 4) `pcov` nebo `xdebug`.

## Tagy cvičení

Jedno cvičení na modul: tag `mNN-start` je výchozí stav, `mNN-end` řešení. Tagy od
`m03-start` po `m12-start` leží na jedné linii ve větvi `kurz`, každý je předkem dalšího.
Záznamy jednotlivých lekcí leží ve větvích `zaznam/NN-L`.

| Tag | Výchozí stav / řešení | `make check` |
|---|---|---|
| `m00-start` | aplikace bez storna objednávky, přesně ve stavu, na kterém běželo měření z lekce 0.2 (včetně tehdejšího README) | PHPUnit |
| `m00-end` | skutečný výsledek ukázkového běhu agenta z lekce 0.2 (`mereni/data/mereni-legacy/r3-opus`) beze změny: storno ve staré administraci, přepis relace v `docs/experiment/` | PHPUnit |
| `m03-start` / `m03-end` | instrukční soubor, skill, pravidlo pro `src/Legacy` / plán a změna množství položky ve staré administraci po krocích, most na command bus | PHPUnit |
| `m04-start` / `m04-end` | Infection na změněných řádcích, CI / limit slevy a odebrání položky | + Infection |
| `m05-start` / `m05-end` | PHPStan max s baseline / hodnotové objekty v Inventory, prázdná baseline, hook `make check-changed` | + PHPStan |
| `m06-start` / `m06-end` | přežitek `Money::getAmountInCents()`, patche běhů r3 a r6 v `cviceni/` / pravidla PHPStanu pro starou administraci, Rector | + `phpstan-legacy`, Rector nanečisto |
| `m07-start` / `m07-end` | Deptrac nainstalovaný, čtyři porušení hranic v `cviceni/m07` / Deptrac po kontextech s vrstvou `Legacy` | + Deptrac, `lint:container`, `schema:validate` |
| `m08-start` / `m08-end` | storno z běhu r3 s dotazy přes `quote()`, popis změny v `docs/pr.md` / review: vrácený zásah do domény, vratka z `paidAmount()`, zadání reviewera | všech 8 kroků |
| `m09-start` / `m09-end` | přehled stornovaných objednávek se šablonou s `raw` / CSRF a `auth_require()` s rolí ve staré administraci, oprávnění agenta, CODEOWNERS | všech 8 kroků |
| `m10-start` / `m10-end` | data pro charakterizační testy / snapshoty, `MonthlyRevenue`, protikorupční vrstva katalogu, mapa staré administrace | všech 8 kroků |
| `m11-start` / `m11-end` | CI jen `composer audit`, PHPUnit a PHPStan, mantinely agenta jen popsané / sdílené nastavení agenta, hooky dalších nástrojů, politika AI | všech 8 kroků lokálně |
| `m12-start` / `m12-end` | všechny mantinely, bez storna / výsledek ukázkového běhu z lekce 12.1 (doplní se po měření 12.1) | všech 8 kroků |

Větve: `kurz` (linie tagů `m03-start` až `m12-start`), `zaznam/03-2` (běh r6 z lekce 0.2
beze změny a nad ním akceptační test storna odeslané objednávky, červený), `zaznam/04-2`
(limit slevy: červené testy, implementace, test hranice; ukazuje do linie, sloučeno do
`m04-end`).

`m00-end` je výsledek experimentu, ne vzorové řešení: jsou v něm chyby, o kterých lekce
mluví.

```bash
git checkout m00-start
git diff m00-start m00-end -- . ':(exclude)docs/experiment'   # co agent změnil
```

## Licence

Kód aplikace je pod licencí MIT (`LICENSE`). Data měření ve složce `mereni/` jsou pod
licencí CC BY 4.0 (`mereni/LICENSE`).

## Kde se aplikace liší od knihy DDD v Symfony

Kód drží konvence knihy (`Order::place()`, `AggregateRoot` s `record()` a `releaseEvents()`,
události v minulém čase bez přípony, `Money` v haléřích s enumem `Currency`, ID přes
`Uuid::v7()`, autowiring po ohraničených kontextech). Odchylky mají důvod v kurzu:

- **Sleva na objednávku.** Kniha slevu nemá. `Order` má vlastnost `discount`,
  `totalAmount()` sčítá položky jako v knize a `paidAmount()` vrací zaplacenou částku po
  slevě, takže má agent co spočítat správně nebo špatně. Kvůli slevě má objednávka i měnu (`Order::place()` má třetí nepovinný
  parametr `Currency`, výchozí CZK).
- **Přechody stavů přes `OrderStatus::canTransitionTo()` i v `cancel()`.** Kniha má
  v `cancel()` výčet `in_array(..., [Shipped, Delivered])`. Kurz používá ve všech
  metodách, které mění `status`, jednu konvenci, protože se o ni opírá vlastní pravidlo
  PHPStanu z modulu 6.
- **Kontext `Inventory` se `StockItem`.** Kniha jinde používá `Warehouse` a
  `InventoryItem`. Kurz potřebuje malý agregát s rezervacemi podle `OrderId`, na který
  se dá ukázat porušení hranice mezi kontexty.
- **Položky objednávky jako vlastnost jen ke čtení.** `$order->items` je property hook,
  který vrací kopii položek; Doctrine mapuje soukromou kolekci `lines`.
- **Katalog zboží** čte tabulku `products` staré administrace přes port
  `ProductCatalog`; vlastní katalog kniha neřeší.
