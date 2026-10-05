<?php

declare(strict_types=1);

namespace App\Ordering\Application\Handler;

use App\Ordering\Application\Command\AddOrderItem;
use App\Ordering\Domain\Repository\OrderRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AddOrderItemHandler
{
    public function __construct(
        private OrderRepository $orders,
    ) {}

    public function __invoke(AddOrderItem $command): void
    {
        $order = $this->orders->get($command->orderId);
        $order->addItem($command->productId, $command->quantity, $command->unitPrice);
        $this->orders->save($order);
    }
}
