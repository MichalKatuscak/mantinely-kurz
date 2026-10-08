<?php

declare(strict_types=1);

namespace App\Tests\Legacy;

use App\Identity\Infrastructure\Security\DemoCustomerProvider;
use App\Tests\Support\Catalog;
use App\Tests\Support\TestDatabase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Role ve staré administraci: auth_require('<role>') pustí jen uživatele s tou rolí
 * (admin smí všechno). Roli staré administrace nese role Symfony uživatele
 * (ROLE_ADMIN, ROLE_OBCHOD, ROLE_SKLAD, ROLE_UCETNI), předává ji LegacyFrontController.
 */
final class AuthRequireTest extends WebTestCase
{
    private const string ORDER = '0192f0a0-1c3e-7d00-8c00-0000000000bb';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        TestDatabase::reset();
        unset($GLOBALS['db']);
        $this->client = self::createClient();
        $this->connection()->insert('orders', [
            'id' => self::ORDER,
            'status' => 'confirmed',
            'placed_at' => '2026-10-01 10:00:00',
            'customer_id' => DemoCustomerProvider::ALICE,
            'currency' => 'CZK',
            'discount_amount_in_cents' => 0,
            'discount_currency' => 'CZK',
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['db']);
        parent::tearDown();
    }

    #[Test]
    public function warehouseRoleCannotChangeOrders(): void
    {
        $this->loginAs('sklad@example.com');
        $token = $this->tokenFrom('/admin/legacy/orders');

        $this->client->request('POST', '/admin/legacy/orders_bulk', [
            'ids' => [self::ORDER],
            'action' => 'paid',
            '_csrf' => $token,
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('Nemáte oprávnění', (string) $this->client->getResponse()->getContent());
        self::assertSame('confirmed', $this->orderStatus());
    }

    #[Test]
    public function salesRoleChangesOrders(): void
    {
        $this->loginAs('obchod@example.com');
        $token = $this->tokenFrom('/admin/legacy/orders');

        $this->client->request('POST', '/admin/legacy/orders_bulk', [
            'ids' => [self::ORDER],
            'action' => 'paid',
            '_csrf' => $token,
        ]);

        self::assertResponseRedirects('/admin/legacy/orders');
        self::assertSame('paid', $this->orderStatus());
    }

    #[Test]
    public function warehouseRoleCannotCancelOrder(): void
    {
        $this->loginAs('sklad@example.com');
        $token = $this->tokenFrom('/admin/legacy/orders');

        $this->client->request('POST', '/admin/legacy/order_cancel?id='.self::ORDER, [
            'reason' => 'zákazník volal',
            '_csrf' => $token,
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('Nemáte oprávnění', (string) $this->client->getResponse()->getContent());
        self::assertSame('confirmed', $this->orderStatus());
    }

    #[Test]
    public function salesRoleCancelsOrder(): void
    {
        $this->loginAs('obchod@example.com');
        $token = $this->tokenFrom('/admin/legacy/orders');

        $this->client->request('POST', '/admin/legacy/order_cancel?id='.self::ORDER, [
            'reason' => 'zákazník volal',
            '_csrf' => $token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('cancelled', $this->orderStatus());
    }

    #[Test]
    public function warehouseRoleCannotChangeItemQuantity(): void
    {
        $this->loginAs('sklad@example.com');
        $token = $this->tokenFrom('/admin/legacy/orders');

        $this->client->request('POST', '/admin/legacy/order_item_quantity?id='.self::ORDER, [
            'product' => Catalog::KEYBOARD,
            'quantity' => '3',
            '_csrf' => $token,
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('Nemáte oprávnění', (string) $this->client->getResponse()->getContent());
    }

    #[Test]
    #[DataProvider('pageAccess')]
    public function pageIsOpenOnlyToItsRole(string $email, string $page, int $status): void
    {
        $this->loginAs($email);

        $this->client->request('GET', '/admin/legacy/'.$page);

        self::assertResponseStatusCodeSame($status);
    }

    /** @return iterable<string, array{string, string, int}> */
    public static function pageAccess(): iterable
    {
        yield 'sklad: dodavatelé (sklad)' => ['sklad@example.com', 'suppliers', 200];
        yield 'sklad: nastavení (admin)' => ['sklad@example.com', 'settings', 403];
        yield 'sklad: newsletter (obchod)' => ['sklad@example.com', 'newsletter', 403];
        yield 'sklad: stránka bez role' => ['sklad@example.com', 'order_list', 200];
        yield 'obchod: newsletter (obchod)' => ['obchod@example.com', 'newsletter', 200];
        yield 'obchod: dodavatelé (sklad)' => ['obchod@example.com', 'suppliers', 403];
        yield 'obchod: kurzy měn (účetní)' => ['obchod@example.com', 'exchange_rates', 403];
        yield 'sklad: inventura (sklad)' => ['sklad@example.com', 'stock_inventory', 200];
        yield 'obchod: inventura (sklad)' => ['obchod@example.com', 'stock_inventory', 403];
        yield 'sklad: skrytí produktu (obchod)' => ['sklad@example.com', 'product_toggle', 403];
        yield 'obchod: vystavení faktury (účetní)' => ['obchod@example.com', 'invoice_issue', 403];
        yield 'admin: nastavení' => ['sprava@example.com', 'settings', 200];
        yield 'admin: dodavatelé' => ['sprava@example.com', 'suppliers', 200];
        yield 'admin: kurzy měn' => ['sprava@example.com', 'exchange_rates', 200];
    }

    #[Test]
    public function stateChangingControllerActionChecksRole(): void
    {
        $this->loginAs('sklad@example.com');
        $token = $this->tokenFrom('/admin/legacy/orders');

        $this->client->request('POST', '/admin/legacy/user_save', ['login' => 'novy', 'role' => 'admin', '_csrf' => $token]);

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->connection()->fetchOne('SELECT id FROM admin_users WHERE login = ?', ['novy']));
    }

    #[Test]
    public function notesAreSignedWithLoggedInUser(): void
    {
        $this->loginAs('obchod@example.com');
        $this->client->request('GET', '/admin/legacy/order_notes?order='.self::ORDER);
        $this->client->submitForm('Přidat poznámku', ['note' => 'Zákazník volal']);

        self::assertSame(
            'obchod@example.com',
            $this->connection()->fetchOne('SELECT author FROM order_notes WHERE order_id = ?', [self::ORDER]),
        );
    }

    private function tokenFrom(string $url): string
    {
        $this->client->request('GET', $url);
        $token = $this->client->getCrawler()->filter('input[name="_csrf"]')->first()->attr('value');
        self::assertIsString($token);

        return $token;
    }

    private function orderStatus(): mixed
    {
        return $this->connection()->fetchOne('SELECT status FROM orders WHERE id = ?', [self::ORDER]);
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }

    private function loginAs(string $email): void
    {
        $user = self::getContainer()->get(DemoCustomerProvider::class)->loadUserByIdentifier($email);
        $this->client->loginUser($user);
    }
}
