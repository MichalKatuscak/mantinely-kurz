<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Repository;

use App\Ordering\Domain\Exception\OrderNotFoundException;
use App\Ordering\Domain\Model\Order;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;

interface OrderRepository
{
    /** @throws OrderNotFoundException */
    public function get(OrderId $id): Order;

    public function find(OrderId $id): ?Order;

    /** @return list<Order> */
    public function findByCustomer(CustomerId $customerId): array;

    public function save(Order $order): void;
}
