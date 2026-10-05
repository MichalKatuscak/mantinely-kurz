<?php

declare(strict_types=1);

namespace App\Ordering\Application\Handler;

use App\Ordering\Application\Command\PlaceOrder;
use App\Ordering\Domain\Model\Order;
use App\Ordering\Domain\Repository\OrderRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class PlaceOrderHandler
{
    public function __construct(
        private OrderRepository $orders,
    ) {}

    public function __invoke(PlaceOrder $command): void
    {
        $order = Order::place($command->orderId, $command->customerId);
        $this->orders->save($order);
    }
}
