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

Jedno cvičení na modul: tag `mNN-start` je výchozí stav, `mNN-end` řešení. Tagy
dalších modulů přibývají, jak kurz vychází.

- `m00-start`: aplikace bez storna objednávky, přesně ve stavu, na kterém běželo měření
  z lekce 0.2 (včetně tehdejšího README).
- `m00-end`: skutečný výsledek ukázkového běhu agenta z lekce 0.2 beze změny, přepis
  relace leží v `docs/experiment/`. Je to výsledek experimentu, ne vzorové řešení: jsou
  v něm chyby, o kterých lekce mluví.

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
