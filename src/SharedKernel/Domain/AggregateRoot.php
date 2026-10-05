<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain;

abstract class AggregateRoot
{
    /** @var list<object> */
    private array $domainEvents = [];

    final protected function record(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    /** @return list<object> */
    final public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
