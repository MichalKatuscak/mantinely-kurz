<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Repository;

use App\Ordering\Domain\Exception\OrderNotFoundException;
use App\Ordering\Domain\Model\Order;
use App\Ordering\Domain\Repository\OrderRepository;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DoctrineOrderRepository implements OrderRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Target('event.bus')]
        private MessageBusInterface $eventBus,
    ) {}

    public function get(OrderId $id): Order
    {
        return $this->find($id) ?? throw OrderNotFoundException::withId($id);
    }

    public function find(OrderId $id): ?Order
    {
        return $this->entityManager->find(Order::class, $id);
    }

    public function findByCustomer(CustomerId $customerId): array
    {
        /** @var list<Order> */
        return $this->entityManager->createQueryBuilder()
            ->select('o')
            ->from(Order::class, 'o')
            ->where('o.customerId = :customerId')
            ->orderBy('o.placedAt', 'DESC')
            ->setParameter('customerId', $customerId->value)
            ->getQuery()
            ->getResult();
    }

    public function findByStatus(string $status): array
    {
        /** @var list<Order> */
        return $this->entityManager
            ->createQuery('SELECT o FROM App\Ordering\Domain\Model\Order o WHERE o.status = :status ORDER BY o.placedAt DESC')
            ->setParameter('status', $status)
            ->getResult();
    }

    public function save(Order $order): void
    {
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Události se publikují až po uložení agregátu.
        foreach ($order->releaseEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
