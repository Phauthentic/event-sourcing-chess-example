<?php

declare(strict_types=1);

namespace App\EventSourcing\EventStore;

use App\Entity\DoctrineEventEntity;
use Doctrine\ORM\EntityManagerInterface;
use Phauthentic\EventStore\EventInterface;
use Iterator;
use Phauthentic\EventStore\EventStoreInterface;
use Phauthentic\EventStore\Serializer\SerializerInterface;

class DoctrineEventStore implements EventStoreInterface
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected SerializerInterface $serializer,
    ) {
    }

    public function storeEvent(EventInterface $event): void
    {
        $entity = new DoctrineEventEntity();

        $entity->setAggregateId($event->getAggregateId());
        $entity->setPayload($this->serializer->serialize($event->getPayload()));
        $entity->setEvent($event->getEvent());
        $entity->setVersion($event->getAggregateVersion());
        $entity->setCreatedAt($event->getCreatedAt()->format('Y-m-d H:i:s'));

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function replyFromPosition(string $aggregateId, int $position = 0): Iterator
    {
        // @todo Implement this
        return new \ArrayIterator([]);
    }
}
