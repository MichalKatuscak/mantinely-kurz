-- Data pro charakterizační testy reportu měsíčních tržeb (cvičení modulu 10).
-- Čtyři situace: měsíc bez objednávek, měsíc se slevou, měsíc se stornem, přelom roku.
-- Částky jsou v haléřích jako v tabulkách aplikace.

-- 2025-10: měsíc se slevou. Položky 2 × 500 + 1 × 200 = 1 200 Kč, sleva 60 Kč (zaplaceno 1 140 Kč).
INSERT INTO orders (id, customer_id, currency, status, placed_at, discount_amount_in_cents, discount_currency)
VALUES ('0192f0a0-3a00-7000-8000-000000000101', '0192f0a0-1c3e-7a11-8c00-000000000001', 'CZK', 'paid', '2025-10-15 10:30:00', 6000, 'CZK');
INSERT INTO order_items (order_id, product_id, quantity, unit_price_amount_in_cents, unit_price_currency)
VALUES ('0192f0a0-3a00-7000-8000-000000000101', '0192f0a0-2b00-7000-8000-00000000000a', 2, 50000, 'CZK'),
       ('0192f0a0-3a00-7000-8000-000000000101', '0192f0a0-2b00-7000-8000-00000000000b', 1, 20000, 'CZK');

-- 2025-11: měsíc bez objednávek.

-- 2025-12 / 2026-01: přelom roku. Jedna objednávka minutu před půlnocí, druhá těsně po ní.
INSERT INTO orders (id, customer_id, currency, status, placed_at, discount_amount_in_cents, discount_currency)
VALUES ('0192f0a0-3a00-7000-8000-000000000201', '0192f0a0-1c3e-7a11-8c00-000000000001', 'CZK', 'paid', '2025-12-31 23:59:00', 0, 'CZK'),
       ('0192f0a0-3a00-7000-8000-000000000202', '0192f0a0-1c3e-7b0b-8c00-000000000002', 'CZK', 'paid', '2026-01-01 00:01:00', 0, 'CZK');
INSERT INTO order_items (order_id, product_id, quantity, unit_price_amount_in_cents, unit_price_currency)
VALUES ('0192f0a0-3a00-7000-8000-000000000201', '0192f0a0-2b00-7000-8000-00000000000a', 1, 40000, 'CZK'),
       ('0192f0a0-3a00-7000-8000-000000000202', '0192f0a0-2b00-7000-8000-00000000000b', 1, 25000, 'CZK');

-- 2026-02: měsíc se stornem. Zaplacená objednávka za 800 Kč a stornovaná za 300 Kč.
INSERT INTO orders (id, customer_id, currency, status, placed_at, discount_amount_in_cents, discount_currency)
VALUES ('0192f0a0-3a00-7000-8000-000000000301', '0192f0a0-1c3e-7a11-8c00-000000000001', 'CZK', 'paid', '2026-02-10 09:00:00', 0, 'CZK'),
       ('0192f0a0-3a00-7000-8000-000000000302', '0192f0a0-1c3e-7b0b-8c00-000000000002', 'CZK', 'cancelled', '2026-02-11 14:00:00', 0, 'CZK');
INSERT INTO order_items (order_id, product_id, quantity, unit_price_amount_in_cents, unit_price_currency)
VALUES ('0192f0a0-3a00-7000-8000-000000000301', '0192f0a0-2b00-7000-8000-00000000000a', 2, 40000, 'CZK'),
       ('0192f0a0-3a00-7000-8000-000000000302', '0192f0a0-2b00-7000-8000-00000000000b', 1, 30000, 'CZK');
