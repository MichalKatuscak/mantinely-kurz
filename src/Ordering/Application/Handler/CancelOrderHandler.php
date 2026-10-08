<?php

declare(strict_types=1);

namespace App\Ordering\Application\Handler;

use App\Ordering\Application\Command\CancelOrder;
use App\Ordering\Domain\Repository\OrderRepository;
use App\Ordering\Domain\ValueObject\OrderStatus;
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

        // Vratku zjistí handler před stornem: zaplacená objednávka vrací, co zaplatila
        // (po slevě), nezaplacená ani už stornovaná nic. Order::cancel() se kvůli ní nemění.
        $refund = $order->status === OrderStatus::Paid
            ? $order->paidAmount()
            : Money::zero($order->currency);

        $order->cancel($command->reason, new \DateTimeImmutable());
        $this->orders->save($order);

        return $refund;
    }
}
