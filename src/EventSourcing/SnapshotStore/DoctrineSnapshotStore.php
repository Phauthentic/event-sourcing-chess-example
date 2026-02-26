<?php

declare(strict_types=1);

namespace App\EventSourcing\SnapshotStore;

use App\Entity\DoctrineSnapshotEntity;
use Doctrine\ORM\EntityManagerInterface;
use Phauthentic\SnapshotStore\SnapshotFactoryInterface;
use Phauthentic\SnapshotStore\SnapshotInterface;
use Phauthentic\SnapshotStore\Store\SnapshotStoreInterface;

class DoctrineSnapshotStore implements SnapshotStoreInterface
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected SnapshotFactoryInterface $snapshotFactory
    ) {
    }

    public function store(SnapshotInterface $snapshot): void
    {
        $entity = new DoctrineSnapshotEntity();

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function get(string $aggregateId): ?SnapshotInterface
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(DoctrineSnapshotEntity::class, 's')
            ->where('s.aggregateId = :aggregateId')
            ->setParameter('aggregateId', $aggregateId)
            ->getQuery()
            ->getOneOrNullResult();

        if ($result === null) {
            return null;
        }

        return $this->snapshotFactory->fromArray([
            SnapshotInterface::AGGREGATE_ID => $result->getAggregateId(),
            SnapshotInterface::AGGREGATE_TYPE => $result->getAggregateType(),
            SnapshotInterface::AGGREGATE_VERSION => $result->getAggregateVersion(),
            SnapshotInterface::AGGREGATE_CREATED_AT => $result->getAggregateCreatedAt(),
            SnapshotInterface::AGGREGATE_ROOT => $result->getAggregateRoot(),
        ]);
    }

    public function delete(string $aggregateId): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(DoctrineSnapshotEntity::class, 's')
            ->where('s.aggregateId = :aggregateId')
            ->setParameter('aggregateId', $aggregateId)
            ->getQuery()
            ->execute();
    }
}
