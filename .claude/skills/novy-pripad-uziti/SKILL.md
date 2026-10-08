---
name: novy-pripad-uziti
description: Použij při přidání nového příkazu nebo změně chování objednávky
---
# Nový případ užití v Orderingu

1. Command bez přípony v `src/Ordering/Application/Command` (např. `ConfirmOrder`)
2. Handler s příponou Handler v `src/Ordering/Application/Handler`, atribut `#[AsMessageHandler(bus: 'command.bus')]`
3. Změna stavu jen přes doménovou metodu `Order`, která ověří přechod a zaznamená událost
4. Test handleru a test agregátu
5. `make check`
