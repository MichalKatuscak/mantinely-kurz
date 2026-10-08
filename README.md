# Mantinely - ukázková aplikace kurzu

Repozitář videokurzu **Mantinely: vývoj s AI v PHP a Symfony**. Na této aplikaci
se dělají cvičení. Doménou navazuje na knihu [DDD v Symfony](https://ddd-v-symfony.katuscak.cz).

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

Jedno cvičení na modul: tag `mNN-start` je výchozí stav, `mNN-end` řešení.
Záznamy jednotlivých lekcí leží ve větvích `zaznam/NN-L`.

## Kde se aplikace liší od knihy DDD v Symfony

Kód drží konvence knihy (`Order::place()`, `AggregateRoot` s `record()` a `releaseEvents()`,
události v minulém čase bez přípony, `Money` v haléřích s enumem `Currency`, ID přes
`Uuid::v7()`, autowiring po ohraničených kontextech). Odchylky mají důvod v kurzu:

- **Sleva na objednávku.** Kniha slevu nemá. `Order` má vlastnost `discount`,
  `totalAmount()` sčítá položky jako v knize a `paidAmount()` vrací zaplacenou částku po
  slevě. Zaplacená částka se tak liší od součtu položek, na čemž stojí limit slevy
  (modul 4). Kvůli slevě má objednávka i měnu (`Order::place()` má třetí nepovinný
  parametr `Currency`, výchozí CZK).
- **Přechody stavů přes `OrderStatus::canTransitionTo()` i v `cancel()`.** Kniha má
  v `cancel()` výčet `in_array(..., [Shipped, Delivered])`. Kurz používá ve všech
  metodách, které mění `status`, jednu konvenci, takže povolené přechody jsou na jednom
  místě (`OrderStatus::allowedTransitions()`).
- **Kontext `Inventory` se `StockItem`.** Kniha jinde používá `Warehouse` a
  `InventoryItem`. Kurz potřebuje malý agregát s rezervacemi podle `OrderId`, na který
  se dá ukázat porušení hranice mezi kontexty.
- **Položky objednávky jako vlastnost jen ke čtení.** `$order->items` je property hook,
  který vrací kopii položek; Doctrine mapuje soukromou kolekci `lines`.
- **Katalog zboží** čte tabulku `products` staré administrace přes port
  `ProductCatalog`; vlastní katalog kniha neřeší.
- **Přežitek `Money::getAmountInCents()`** (od `m06-start` do `m06-end`). Kniha čte
  `$money->amountInCents`. Getter s 32 voláními, z toho pět na proměnných bez typu, je tu
  záměrně: lekce 6.3 na něm ukazuje vlastní pravidlo pro Rector
  (`tools/Rector`) a v `m06-end` getter mizí.
- **Deptrac místo phparkitect.** Kniha ukazuje pro testy architektury phparkitect
  (`/mene-zname-vzory#mod-phparkitect`). Kurz volí Deptrac (`deptrac.php`, balíček
  `deptrac/deptrac` 4.x): pravidla jsou deklarativní mapa vrstev, kterou agent přečte
  i bez spuštění, výstup jmenuje porušenou dvojici vrstev a `--fail-on-uncovered` chytí
  i třídu v adresáři, který nikdo nezařadil (lekce 7.3).
- **Vrstvy po ohraničených kontextech.** Knižní `deptrac.php` dělí vrstvy podle technické
  role (Domain, Application, Infrastructure…), takže aplikační vrstva smí na celou doménu
  a nepozná, že `StockItem` patří jinému kontextu. Tady jsou vrstvy `OrderingDomain`,
  `OrderingApplication`, `InventoryDomain`… a zvlášť `OrderingEvents`, `OrderingErrors`,
  `Identifiers` a `Persistence` (`Connection`, `EntityManagerInterface`, `QueryBuilder`).
  Stará administrace má vrstvu `Legacy`, která smí do nového kódu jen příkazem Orderingu
  (s ID a výjimkami, které příkaz hází); na ni nesmí žádná vrstva nového kódu.
