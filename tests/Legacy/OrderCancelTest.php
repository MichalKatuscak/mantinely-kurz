<?php

declare(strict_types=1);

namespace App\Tests\Legacy;

use App\Identity\Infrastructure\Security\DemoCustomerProvider;
use App\Legacy\Http\LegacyFrontController;
use App\Tests\Support\Catalog;
use App\Tests\Support\TestDatabase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversClass(LegacyFrontController::class)]
final class OrderCancelTest extends WebTestCase
{
    private const string ORDER = '0192f0a0-2b00-7000-8000-0000000000a1';
    private const string OTHER_ORDER = '0192f0a0-2b00-7000-8000-0000000000a2';

    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->client = self::createClient();
        $this->db = self::getContainer()->get(Connection::class);
        Catalog::seed($this->db);
        $user = self::getContainer()->get(DemoCustomerProvider::class)->loadUserByIdentifier('sprava@example.com');
        $this->client->loginUser($user);
    }

    #[Test]
    public function cancellingPaidOrderRefundsPaidAmountAndReleasesStock(): void
    {
        $this->seedOrder(self::ORDER, 'paid', 1000);
        $this->seedOrder(self::OTHER_ORDER, 'paid', 0);

        $this->client->request('POST', '/admin/legacy/order_cancel', ['id' => self::ORDER, 'reason' => 'Zákazník si to rozmyslel']);

        self::assertSame('cancelled', $this->db->fetchOne('SELECT status FROM orders WHERE id = ?', [self::ORDER]));
        self::assertSame('paid', $this->db->fetchOne('SELECT status FROM orders WHERE id = ?', [self::OTHER_ORDER]));

        // 2 × 500 Kč − sleva 10 Kč = 990 Kč se vrací, rezervace cizí objednávky zůstává
        $payload = json_decode((string) $this->db->fetchOne("SELECT payload FROM audit_log WHERE action = 'storno'"), true);
        self::assertSame(99000, $payload['refund']);
        self::assertSame(2, $payload['released']);
        $reservations = json_decode((string) $this->db->fetchOne('SELECT reservations FROM stock_items WHERE product_id = ?', [Catalog::KEYBOARD]), true);
        self::assertSame([self::OTHER_ORDER => 2], $reservations);
    }

    #[Test]
    public function unpaidOrderIsCancelledWithoutRefund(): void
    {
        $this->seedOrder(self::ORDER, 'confirmed', 0);

        $this->client->request('POST', '/admin/legacy/order_cancel', ['id' => self::ORDER]);

        $payload = json_decode((string) $this->db->fetchOne("SELECT payload FROM audit_log WHERE action = 'storno'"), true);
        self::assertSame(0, $payload['refund']);
    }

    #[Test]
    public function deliveredOrderCannotBeCancelled(): void
    {
        $this->seedOrder(self::ORDER, 'delivered', 0);

        $this->client->request('POST', '/admin/legacy/order_cancel', ['id' => self::ORDER]);

        self::assertSame('delivered', $this->db->fetchOne('SELECT status FROM orders WHERE id = ?', [self::ORDER]));
    }

    private function seedOrder(string $id, string $status, int $discountCents): void
    {
        $this->db->insert('orders', [
            'id' => $id,
            'customer_id' => DemoCustomerProvider::ALICE,
            'status' => $status,
            'currency' => 'CZK',
            'discount_amount_in_cents' => $discountCents,
            'discount_currency' => 'CZK',
            'placed_at' => '2026-10-01 10:00:00',
        ]);
        $this->db->insert('order_items', [
            'order_id' => $id,
            'product_id' => Catalog::KEYBOARD,
            'quantity' => 2,
            'unit_price_amount_in_cents' => 50000,
            'unit_price_currency' => 'CZK',
        ]);
        $existing = json_decode((string) $this->db->fetchOne('SELECT reservations FROM stock_items WHERE product_id = ?', [Catalog::KEYBOARD]), true) ?: [];
        $existing[$id] = 2;
        $this->db->update('stock_items', ['reservations' => json_encode($existing)], ['product_id' => Catalog::KEYBOARD]);
    }
}
