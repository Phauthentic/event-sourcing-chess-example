<?php

declare(strict_types=1);

namespace App\EventSourcing;

use Phauthentic\EventSourcing\Repository\AggregateExtractor\AttributeBasedExtractor;
use Phauthentic\EventSourcing\Repository\EventSourcedRepository;
use Phauthentic\EventSourcing\Repository\EventSourcedRepositoryInterface;
use Phauthentic\EventStore\EventStoreInterface;

class RepositoryFactory
{
    public function __construct(
        private EventStoreInterface $eventStore,
    ) {
    }

    public function createEventSourcedRepository(): EventSourcedRepositoryInterface
    {
        return new EventSourcedRepository(
            eventStore: $this->eventStore,
            snapshotStore: null,
            aggregateExtractor: new AttributeBasedExtractor()
        );
    }
}
