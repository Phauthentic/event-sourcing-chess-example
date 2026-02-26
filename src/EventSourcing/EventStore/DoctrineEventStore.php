<?php

declare(strict_types=1);

namespace App\EventSourcing\EventStore;

use App\Entity\DoctrineEventEntity;
use Doctrine\ORM\EntityManagerInterface;
use Phauthentic\EventStore\Event;
use Phauthentic\EventStore\EventInterface;
use Phauthentic\EventStore\EventStoreInterface;
use Phauthentic\EventStore\ReplyFromPositionQuery;
use Phauthentic\EventStore\Serializer\SerializerInterface;
use Iterator;

class DoctrineEventStore implements EventStoreInterface
{
    private const CREATED_AT_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected SerializerInterface $serializer,
    ) {
    }

    public function storeEvent(EventInterface $event): void
    {
        $entity = new DoctrineEventEntity();
        $entity->setStream($event->getStream() ?? $event->getAggregateId());
        $entity->setAggregateId($event->getAggregateId());
        $entity->setPayload($this->serializer->serialize($event->getPayload()));
        $entity->setEvent($event->getEvent());
        $entity->setVersion($event->getAggregateVersion());
        $entity->setCreatedAt($event->getCreatedAt()->format(self::CREATED_AT_FORMAT));

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function replyFromPosition(ReplyFromPositionQuery $fromPositionQuery): Iterator
    {
        $repository = $this->entityManager->getRepository(DoctrineEventEntity::class);

        $qb = $repository->createQueryBuilder('e')
            ->where('e.aggregateId = :aggregateId')
            ->andWhere('e.version >= :position')
            ->setParameter('aggregateId', $fromPositionQuery->aggregateId)
            ->setParameter('position', $fromPositionQuery->position)
            ->orderBy('e.version', 'ASC');

        $entities = $qb->getQuery()->getResult();

        foreach ($entities as $entity) {
            $createdAt = \DateTimeImmutable::createFromFormat(
                self::CREATED_AT_FORMAT,
                $entity->getCreatedAt()
            );
            if ($createdAt === false) {
                $createdAt = new \DateTimeImmutable($entity->getCreatedAt());
            }

            yield new Event(
                aggregateId: $entity->getAggregateId(),
                aggregateVersion: $entity->getVersion(),
                event: $entity->getEvent(),
                payload: $this->serializer->unserialize($entity->getPayload()),
                createdAt: $createdAt
            );
        }
    }
}
