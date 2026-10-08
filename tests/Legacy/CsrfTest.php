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
 * Akce staré administrace, které mění data, přijmou POST jen s tokenem z formuláře
 * (csrf_field() ve formuláři, csrf_check() v akci). Bez tokenu odpoví 403 a nic nezmění.
 */
final class CsrfTest extends WebTestCase
{
    private const string ORDER = '0192f0a0-1c3e-7d00-8c00-0000000000aa';

    private const string CSRF_MESSAGE = 'Neplatný bezpečnostní token formuláře';

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
        $this->loginAs('sprava@example.com');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['db']);
        parent::tearDown();
    }

    #[Test]
    public function orderEditWithoutTokenIsRejected(): void
    {
        $this->client->request('POST', '/admin/legacy/order_edit', ['id' => self::ORDER, 'status' => 'paid']);

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString(self::CSRF_MESSAGE, (string) $this->client->getResponse()->getContent());
        self::assertSame('confirmed', $this->orderStatus());
    }

    #[Test]
    public function orderEditWithWrongTokenIsRejected(): void
    {
        $this->client->request('POST', '/admin/legacy/order_edit', [
            'id' => self::ORDER,
            'status' => 'paid',
            '_csrf' => str_repeat('a', 64),
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('confirmed', $this->orderStatus());
    }

    #[Test]
    public function orderEditFormCarriesTokenThatIsAccepted(): void
    {
        $this->client->request('GET', '/admin/legacy/order_edit?id='.self::ORDER);
        $this->client->submitForm('Uložit', ['status' => 'paid']);

        self::assertResponseIsSuccessful();
        self::assertSame('paid', $this->orderStatus());
    }

    #[Test]
    public function bulkActionWithoutTokenIsRejected(): void
    {
        $this->client->request('POST', '/admin/legacy/orders_bulk', ['ids' => [self::ORDER], 'action' => 'paid']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('confirmed', $this->orderStatus());
    }

    #[Test]
    public function bulkFormInOrderListCarriesTokenThatIsAccepted(): void
    {
        $crawler = $this->client->request('GET', '/admin/legacy/orders');
        $token = $crawler->filter('form[action="/admin/legacy/orders_bulk"] input[name="_csrf"]')->attr('value');

        $this->client->request('POST', '/admin/legacy/orders_bulk', [
            'ids' => [self::ORDER],
            'action' => 'paid',
            '_csrf' => $token,
        ]);

        self::assertResponseRedirects('/admin/legacy/orders');
        self::assertSame('paid', $this->orderStatus());
    }

    #[Test]
    public function tokenStaysTheSameWithinSession(): void
    {
        $first = $this->client->request('GET', '/admin/legacy/order_edit?id='.self::ORDER)
            ->filter('input[name="_csrf"]')->attr('value');
        $second = $this->client->request('GET', '/admin/legacy/order_notes?order='.self::ORDER)
            ->filter('input[name="_csrf"]')->attr('value');

        self::assertNotNull($first);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
        self::assertSame($first, $second);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[Test]
    #[DataProvider('statefulActions')]
    public function everyStatefulActionRejectsPostWithoutToken(string $page, array $parameters): void
    {
        $this->client->request('POST', '/admin/legacy/'.$page, $parameters);

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString(self::CSRF_MESSAGE, (string) $this->client->getResponse()->getContent());
    }

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function statefulActions(): iterable
    {
        yield 'hromadné akce s objednávkami' => ['orders_bulk', ['ids' => [self::ORDER], 'action' => 'paid']];
        yield 'úprava objednávky' => ['order_edit', ['id' => self::ORDER, 'status' => 'paid']];
        yield 'poznámka k objednávce' => ['order_notes', ['order' => self::ORDER, 'note' => 'poznámka']];
        yield 'změna množství položky' => ['order_item_quantity?id='.self::ORDER, ['product' => Catalog::KEYBOARD, 'quantity' => '3']];
        yield 'úprava zákazníka' => ['customer_edit', ['email' => 'novy@example.com']];
        yield 'smazání zákazníka' => ['customer_delete', ['id' => DemoCustomerProvider::ALICE]];
        yield 'anonymizace zákazníka' => ['customer_anonymize', ['id' => DemoCustomerProvider::ALICE]];
        yield 'úprava produktu' => ['product_edit', ['name' => 'Produkt', 'price' => '100']];
        yield 'hromadná změna cen' => ['products_bulk_price', ['ids' => ['x'], 'percent' => '10']];
        yield 'inventura' => ['stock_inventory', ['qty' => ['x' => '1']]];
        yield 'uložení uživatele' => ['user_save', ['login' => 'novy', 'role' => 'obchod']];
        yield 'smazání uživatele' => ['user_delete', ['id' => '1']];
        yield 'nastavení' => ['settings', ['new_name' => 'shop_open', 'new_value' => '0']];
        yield 'dodavatelé' => ['suppliers', ['akce' => 'ulozit', 'name' => 'Dodavatel']];
        yield 'kurzy měn' => ['exchange_rates', ['rate' => ['EUR' => '30']]];
        yield 'newsletter' => ['newsletter', ['subject' => 'Předmět', 'body' => 'Text']];
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
