<?php

declare(strict_types=1);

namespace App\Ordering\Application\Handler;

use App\Ordering\Application\Command\ApplyDiscount;
use App\Ordering\Domain\Repository\OrderRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ApplyDiscountHandler
{
    public function __construct(
        private OrderRepository $orders,
    ) {}

    public function __invoke(ApplyDiscount $command): void
    {
        $order = $this->orders->get($command->orderId);
        $order->applyDiscount($command->discount);
        $this->orders->save($order);
    }
}
