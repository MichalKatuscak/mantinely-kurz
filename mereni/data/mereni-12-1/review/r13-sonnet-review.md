# Review: storno ve staré administraci (`git diff m12-start HEAD`)

Našel jsem jeden problém ve správnosti, jeden nesplněný požadavek, jednu nepřesnost v UI a dvě nepřesnosti v autorově zprávě. Hlavní problém: částka k vrácení se nepočítá z toho, co zákazník opravdu zaplatil.

`make check` jsem nespustil, protože příkaz čekal na schválení, které nepřišlo. Všechno níže je tedy ze čtení kódu, testy jsem neověřil.

## Nálezy

**1. Částka k vrácení nemusí být to, co zákazník zaplatil, a může být i záporná**
`src/Ordering/Domain/Model/Order.php:249`
- `refundDueInCents` se počítá až při stornu z aktuálního `paidAmount()`, tedy z dnešních položek a slevy.
- Stará administrace ale mění slevu u objednávky v jakémkoli stavu, tedy i u zaplacené (`src/Legacy/Admin/order_edit.php:67-77`). Povolí i slevu vyšší než součet položek, jen na to upozorní (ř. 73-75).
- Příklad: objednávka za 1 000 Kč se zaplatí, potom obchodník změní slevu na 1 200 Kč a stornuje. Doména zaznamená −200 Kč „k vrácení“ a detail to tak zobrazí.
- Zaplacená částka se v okamžiku `markPaid()` nikde neukládá, takže zadání „zákazník dostane zpět zaplacenou částku“ tahle změna spolehlivě nesplní. Test `OrderRefundTest` pokrývá jen slevu nastavenou v draftu.

**2. Storno zaplacené objednávky jde dál udělat bez vrácení peněz a bez vrácení zboží**
`src/Legacy/Admin/order_edit.php:44`, `src/Legacy/Admin/orders.php:30`
- Stejný detail objednávky dál nabízí úpravu stavu. Ta změní stav na `cancelled` přímo přes `UPDATE orders`, takže nevznikne `OrderCancelled`, nezaznamená se částka k vrácení a neuvolní se rezervace (TODO na ř. 60).
- Ve stejném místě jsou teď dvě cesty ke stornu s různým výsledkem, takže zadání „storno u objednávky vrátí peníze i zboží“ platí jen pro novou.
- Autor zmiňuje jen hromadné storno v `orders.php`. Úpravu stavu v `order_edit.php` nezmiňuje, přitom je dostupná přímo z detailu objednávky.

**3. „K vrácení zákazníkovi“ zůstane vidět i u obnovené objednávky**
`src/Legacy/templates/orders/detail.php:21`
- Podmínka kontroluje jen `refund_due_in_cents !== null`, ne stav objednávky.
- Admin smí stornovanou objednávku vrátit do jiného stavu (`order_edit.php:41-44`). Sloupec `refund_due_in_cents` ale zůstane vyplněný, takže detail zaplacené objednávky dál hlásí částku k vrácení.

**4. Zpráva autora nesedí s repozitářem: „nezacommitované“**
`docs/pr.md`, ř. „Storno je hotové, ale nezacommitované“
- Změna je zacommitovaná (HEAD `1d7b3d6`, „změna od agenta“) a commit nemá avizovaný řádek `Co-Authored-By`.
- Projekt chce po každém kroku commit a předtím `make check`. Že `make check` (119 testů, PHPStan, Rector, Deptrac) opravdu proběhl nad tímhle stavem, jsem nezjistil.

**5. Zpráva autora a hláška nadsazují „zboží vráceno na sklad“**
`src/Legacy/Admin/OrderController.php:196`
- Ve skutečnosti se jen uvolní rezervace, které vznikly přes `OrderItemAdded` (`ReserveStockHandler`). U objednávky bez rezervací se na skladě nic nezmění, a hláška přesto oznámí vrácení zboží.
- Autor to v sekci „Na co si dát pozor“ zčásti přiznává. Hláška pro obsluhu to ale neříká.

## Prošlo bez nálezu
- **CSRF a oprávnění:** `cancelAction` volá nejdřív `auth_require('obchod')` a pak `csrf_check()`. Obě kontroly opravdu něco kontrolují: `auth_require` odmítne nepřihlášeného uživatele i chybějící roli, `csrf_check` porovnává token přes `hash_equals`. Formulář je POST s `csrf_field()` a GET jen přesměruje.
- **SQL:** nový dotaz používá `$db->quote($id)` (`OrderController.php:177`).
- **Stav objednávky a skladu:** mění se jen přes command `CancelOrder` → `Order::cancel()` → událost `OrderCancelled` → `ReleaseReservationsHandler`. Přechody hlídá `canTransitionTo()`. Legacy používá jen command, ID a výjimku z `Domain/Exception`, což pravidla dovolují.
- **Nežádané změny:** signatury `Order` a `OrderCancelled` zůstaly stejné. Existující testy ani konfigurace se neměnily. Nový sloupec přidává vygenerovaná migrace, stejně jako předchozí `cancellation_note`.
- **Duplicitní výpočet:** částka se bere z existujícího `paidAmount()`, žádný nový paralelní výpočet tržeb nevzniká. To, že stornovaná objednávka vypadne z reportu tržeb (filtr na `paid`), je dosavadní chování a autor ho popisuje správně.