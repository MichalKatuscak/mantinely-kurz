<?php

declare(strict_types=1);

namespace App\Tests\Ordering\Infrastructure;

use App\Identity\Infrastructure\Security\DemoCustomerProvider;
use App\Ordering\Domain\Model\Order;
use App\Ordering\Domain\Repository\OrderRepository;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\OrderStatus;
use App\Ordering\Infrastructure\Http\OrderController;
use App\Tests\Support\Catalog;
use App\Tests\Support\TestDatabase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[CoversClass(OrderController::class)]
final class OrderControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->client = self::createClient();
        Catalog::seed(self::getContainer()->get(Connection::class));
    }

    #[Test]
    public function customerPlacesConfirmsAndPaysOrder(): void
    {
        $this->loginAs('alice@example.com');

        $this->client->request('GET', '/objednavky');
        $this->client->submitForm('Nová objednávka');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Objednávka');

        $this->client->submitForm('Přidat', ['productId' => Catalog::KEYBOARD, 'quantity' => 2]);
        $this->client->followRedirect();
        $this->client->submitForm('Potvrdit objednávku');
        $this->client->followRedirect();
        $this->client->submitForm('Zaplatit');
        $this->client->followRedirect();

        self::assertSelectorTextContains('strong', 'paid');
        self::assertSame(OrderStatus::Paid, $this->orderFromUrl()->status);
    }

    #[Test]
    public function foreignCustomerDoesNotSeeOrder(): void
    {
        $this->loginAs('alice@example.com');
        $this->client->request('GET', '/objednavky');
        $this->client->submitForm('Nová objednávka');
        $detailUrl = (string) $this->client->getResponse()->headers->get('Location');

        $this->loginAs('bob@example.com');
        $this->client->request('GET', $detailUrl);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function anonymousVisitorMustLogIn(): void
    {
        $this->client->request('GET', '/objednavky');

        self::assertResponseStatusCodeSame(401);
    }

    private function loginAs(string $email): void
    {
        $user = self::getContainer()->get(DemoCustomerProvider::class)->loadUserByIdentifier($email);
        $this->client->loginUser($user);
    }

    private function orderFromUrl(): Order
    {
        $path = (string) parse_url($this->client->getRequest()->getUri(), PHP_URL_PATH);
        $id = basename($path);

        return self::getContainer()->get(OrderRepository::class)->get(OrderId::fromString($id));
    }
}
