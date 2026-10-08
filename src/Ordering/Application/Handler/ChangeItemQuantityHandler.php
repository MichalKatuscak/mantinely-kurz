<?php

declare(strict_types=1);

namespace App\Ordering\Application\Handler;

use App\Ordering\Application\Command\ChangeItemQuantity;
use App\Ordering\Domain\Repository\OrderRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ChangeItemQuantityHandler
{
    public function __construct(
        private OrderRepository $orders,
    ) {}

    public function __invoke(ChangeItemQuantity $command): void
    {
        $order = $this->orders->get($command->orderId);
        $order->changeItemQuantity($command->productId, $command->quantity);
        $this->orders->save($order);
    }
}
