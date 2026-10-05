<?php

declare(strict_types=1);

namespace App\Ordering\Application\Handler;

use App\Ordering\Application\Command\PayOrder;
use App\Ordering\Domain\Repository\OrderRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class PayOrderHandler
{
    public function __construct(
        private OrderRepository $orders,
    ) {}

    public function __invoke(PayOrder $command): void
    {
        $order = $this->orders->get($command->orderId);
        $order->markPaid();
        $this->orders->save($order);
    }
}
