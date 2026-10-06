<?php

declare(strict_types=1);

namespace App\Ordering\Application\Handler;

use App\Ordering\Application\Command\CancelOrder;
use App\Ordering\Domain\Repository\OrderRepository;
use App\SharedKernel\Domain\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Stornuje objednávku. Rezervace ve skladu uvolní Inventory podle OrderCancelled.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CancelOrderHandler
{
    public function __construct(
        private OrderRepository $orders,
    ) {}

    /** Vrací částku, kterou je třeba zákazníkovi vrátit. */
    public function __invoke(CancelOrder $command): Money
    {
        $order = $this->orders->get($command->orderId);
        $refund = $order->cancel($command->reason, new \DateTimeImmutable());
        $this->orders->save($order);

        return $refund;
    }
}
