<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use Phauthentic\EventSourcing\Repository\AggregateExtractor\AttributeBasedExtractor;
use Phauthentic\EventSourcing\Repository\AggregateFactory\ReflectionFactory;
use Phauthentic\EventSourcing\Repository\EventSourcedRepositoryFactoryInterface;
use Phauthentic\EventSourcing\Repository\EventSourcedRepository;
use Phauthentic\EventStore\EventFactory;
use Phauthentic\EventStore\PdoEventStore;
use Phauthentic\EventStore\Serializer\SerializeSerializer as EventStoreSerializeSerializer;
use Phauthentic\SnapshotStore\Serializer\SerializeSerializer as SnapshotSerializeSerializer;
use Phauthentic\SnapshotStore\SnapshotFactory;
use Phauthentic\SnapshotStore\Store\PdoSqlSnapshotStore;

/**
 *
 */
class EventSourcedRepositoryFactory implements EventSourcedRepositoryFactoryInterface
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function create()
    {
        return $this->createRepositoryFromClassString(EventSourcedRepository::class);
    }

    public function createRepositoryFromClassString(string $class): EventSourcedRepository
    {
        return new EventSourcedRepository(
            eventStore: new PdoEventStore(
                $this->pdo,
                new EventStoreSerializeSerializer(),
                new EventFactory()
            ),
            aggregateExtractor: new AttributeBasedExtractor(),
            aggregateFactory: new ReflectionFactory(),
            eventFactory: new EventFactory(),
            snapshotStore: new PdoSqlSnapshotStore(
                $this->pdo,
                new SnapshotSerializeSerializer()
            ),
            snapshotFactory: new SnapshotFactory()
        );
    }
}
