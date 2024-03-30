<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "event_store_snapshots")]
class DoctrineSnapshotEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 255)]
    private string $aggregateType;

    #[ORM\Column(type: "string", length: 36)]
    private string $aggregateId;

    #[ORM\Column(type: "integer")]
    private int $aggregateVersion;

    #[ORM\Column(type: "text")]
    private string $aggregateRoot;

    #[ORM\Column(type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTimeInterface $createdAt;

    // Getters and setters for each property
    public function getId(): int
    {
        return $this->id;
    }

    public function getAggregateType(): string
    {
        return $this->aggregateType;
    }

    public function setAggregateType(string $aggregateType): void
    {
        $this->aggregateType = $aggregateType;
    }

    public function getAggregateId(): string
    {
        return $this->aggregateId;
    }

    public function setAggregateId(string $aggregateId): void
    {
        $this->aggregateId = $aggregateId;
    }

    public function getAggregateVersion(): int
    {
        return $this->aggregateVersion;
    }

    public function setAggregateVersion(int $aggregateVersion): void
    {
        $this->aggregateVersion = $aggregateVersion;
    }

    public function getAggregateRoot(): string
    {
        return $this->aggregateRoot;
    }

    public function setAggregateRoot(string $aggregateRoot): void
    {
        $this->aggregateRoot = $aggregateRoot;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
