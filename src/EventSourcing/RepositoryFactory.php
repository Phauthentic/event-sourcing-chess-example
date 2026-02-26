<?php

declare(strict_types=1);

namespace App\EventSourcing;

use Phauthentic\EventSourcing\Projection\ProjectorInterface;
use Phauthentic\EventSourcing\Repository\AggregateExtractor\AttributeBasedExtractor;
use Phauthentic\EventSourcing\Repository\AggregateFactory\ReflectionFactory;
use Phauthentic\EventSourcing\Repository\EventPublisher\EventPublisher;
use Phauthentic\EventSourcing\Repository\EventPublisher\EventPublisherInterface;
use Phauthentic\EventSourcing\Repository\EventPublisher\SynchronousProjectorMiddleware;
use Phauthentic\EventSourcing\Repository\EventPublisher\SymfonyMessageBusConnectorMiddleware;
use Phauthentic\EventSourcing\Repository\EventSourcedRepository;
use Phauthentic\EventSourcing\Repository\EventSourcedRepositoryInterface;
use Phauthentic\EventStore\EventFactory;
use Phauthentic\EventStore\EventStoreInterface;
use Phauthentic\SnapshotStore\SnapshotFactory;
use Phauthentic\SnapshotStore\Store\NullSnapshotStore;
use Symfony\Component\Messenger\MessageBusInterface;

class RepositoryFactory
{
    /** @var array<ProjectorInterface> */
    private array $projectors = [];

    public function __construct(
        private EventStoreInterface $eventStore,
        private ?MessageBusInterface $domainEventMessageBus = null,
        iterable $projectors = [],
    ) {
        $this->projectors = is_array($projectors) ? $projectors : iterator_to_array($projectors);
    }

    public function createEventSourcedRepository(): EventSourcedRepositoryInterface
    {
        $publisher = $this->createEventPublisher();

        return new EventSourcedRepository(
            eventStore: $this->eventStore,
            aggregateExtractor: new AttributeBasedExtractor(),
            aggregateFactory: new ReflectionFactory(),
            eventFactory: new EventFactory(),
            snapshotStore: new NullSnapshotStore(),
            snapshotFactory: new SnapshotFactory(),
            eventPublisher: $publisher
        );
    }

    private function createEventPublisher(): EventPublisherInterface
    {
        $middlewares = [];

        // Add synchronous projector middleware if projectors are configured
        if (!empty($this->projectors)) {
            $syncProjectorMiddleware = new SynchronousProjectorMiddleware(
                projectors: $this->projectors,
                failFast: true // Fail fast for critical projections like board state
            );
            $middlewares[] = $syncProjectorMiddleware;
        }

        // Add async message bus middleware if configured
        if ($this->domainEventMessageBus !== null) {
            $asyncMiddleware = new SymfonyMessageBusConnectorMiddleware($this->domainEventMessageBus);
            $middlewares[] = $asyncMiddleware;
        }

        return new EventPublisher($middlewares);
    }
}
