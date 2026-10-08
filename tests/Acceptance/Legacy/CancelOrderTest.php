<?php

declare(strict_types=1);

namespace App\Tests\Acceptance\Legacy;

use App\Identity\Infrastructure\Security\DemoCustomerProvider;
use App\Inventory\Domain\Repository\StockItemRepository;
use App\Ordering\Domain\Model\Order;
use App\Ordering\Domain\Repository\OrderRepository;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\OrderStatus;
use App\Ordering\Domain\ValueObject\ProductId;
use App\SharedKernel\Domain\Currency;
use App\SharedKernel\Domain\Money;
use App\Tests\Support\Catalog;
use App\Tests\Support\TestDatabase;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Storno ve staré administraci: zákazník dostane zpět zaplacenou částku
 * (zaznamenanou k ručnímu vrácení) a zboží se vrátí na sklad.
 */
final class CancelOrderTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        TestDatabase::reset();
        // stará administrace drží spojení v globální proměnné
        $GLOBALS['db'] = null;
        $this->client = self::createClient();
        $this->client->disableReboot();
        Catalog::seed(self::getContainer()->get(Connection::class));
    }

    #[Test]
    public function paidOrderIsCancelledFromOrderDetail(): void
    {
        $this->loginAs('obchod@example.com');
        $order = $this->paidOrder();

        $this->client->request('GET', '/admin/legacy/order?id='.$order->id->value);
        $this->client->submitForm('Stornovat', ['reason' => 'Zákazník si to rozmyslel']);

        self::assertSelectorTextContains('.flash', 'Objednávka stornována');
        self::assertSelectorTextContains('body', 'Vrátit zákazníkovi');
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $cancelled = self::getContainer()->get(OrderRepository::class)->get($order->id);
        self::assertSame(OrderStatus::Cancelled, $cancelled->status);
        self::assertSame('Zákazník si to rozmyslel', $cancelled->cancellationNote);
        self::assertTrue($cancelled->refund->equals(new Money(500_00, Currency::CZK)));
        self::assertSame(
            10,
            self::getContainer()->get(StockItemRepository::class)->get(ProductId::fromString(Catalog::KEYBOARD))->available(),
        );
    }

    #[Test]
    public function shippedOrderStaysUnchanged(): void
    {
        $this->loginAs('obchod@example.com');
        $order = $this->paidOrder();
        $order->ship();
        self::getContainer()->get(OrderRepository::class)->save($order);

        $this->client->request('POST', '/admin/legacy/order_cancel?id='.$order->id->value, ['reason' => '', '_csrf' => $this->csrfToken()]);

        self::assertSelectorTextContains('.flash.error', 'stornovat nejde');
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $unchanged = self::getContainer()->get(OrderRepository::class)->get($order->id);
        self::assertSame(OrderStatus::Shipped, $unchanged->status);
        self::assertSame(0, $unchanged->refund->amountInCents);
    }

    #[Test]
    public function warehouseRoleCannotCancel(): void
    {
        $this->loginAs('sklad@example.com');
        $order = $this->paidOrder();

        $this->client->request('POST', '/admin/legacy/order_cancel?id='.$order->id->value, ['reason' => '', '_csrf' => $this->csrfToken()]);

        self::assertResponseStatusCodeSame(403);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame(OrderStatus::Paid, self::getContainer()->get(OrderRepository::class)->get($order->id)->status);
    }

    #[Test]
    public function cancelWithoutCsrfTokenIsRejected(): void
    {
        $this->loginAs('obchod@example.com');
        $order = $this->paidOrder();

        $this->client->request('POST', '/admin/legacy/order_cancel?id='.$order->id->value, ['reason' => '']);

        self::assertResponseStatusCodeSame(403);
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::assertSame(OrderStatus::Paid, self::getContainer()->get(OrderRepository::class)->get($order->id)->status);
    }

    private function paidOrder(): Order
    {
        // klávesnice 2 × 300 Kč, sleva 100 Kč: zaplaceno 500 Kč
        $order = Order::place(OrderId::generate(), CustomerId::generate());
        $order->addItem(ProductId::fromString(Catalog::KEYBOARD), 2, new Money(300_00, Currency::CZK));
        $order->applyDiscount(new Money(100_00, Currency::CZK));
        $order->confirm();
        $order->markPaid();
        self::getContainer()->get(OrderRepository::class)->save($order);

        return $order;
    }

    private function loginAs(string $email): void
    {
        $this->client->loginUser(
            self::getContainer()->get(DemoCustomerProvider::class)->loadUserByIdentifier($email),
        );
    }

    private function csrfToken(): ?string
    {
        return $this->client->request('GET', '/admin/legacy/orders')->filter('input[name="_csrf"]')->first()->attr('value');
    }
}
