<?php

declare(strict_types=1);

namespace App\Ordering\Application\Handler;

use App\Ordering\Application\Command\CancelOrder;
use App\Ordering\Domain\Repository\OrderRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Storno: zaplacená částka se zaznamená k vrácení, rezervace na skladě
 * uvolní Inventory po události OrderCancelled.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CancelOrderHandler
{
    public function __construct(
        private OrderRepository $orders,
    ) {}

    public function __invoke(CancelOrder $command): void
    {
        $order = $this->orders->get($command->orderId);
        $order->cancelWithRefund($command->reason, new \DateTimeImmutable());
        $this->orders->save($order);
    }
}
