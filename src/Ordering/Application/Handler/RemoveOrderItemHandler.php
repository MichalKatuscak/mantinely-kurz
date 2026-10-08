<?php

declare(strict_types=1);

namespace App\Ordering\Application\Handler;

use App\Ordering\Application\Command\RemoveOrderItem;
use App\Ordering\Domain\Repository\OrderRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveOrderItemHandler
{
    public function __construct(
        private OrderRepository $orders,
    ) {}

    public function __invoke(RemoveOrderItem $command): void
    {
        $order = $this->orders->get($command->orderId);
        $order->removeItem($command->productId);
        $this->orders->save($order);
    }
}
