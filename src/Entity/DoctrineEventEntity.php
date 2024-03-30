<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'event_store')]
class DoctrineEventEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 128)]
    private string $stream;

    #[ORM\Column(type: "string", length: 36)]
    private string $aggregateId;

    #[ORM\Column(type: "integer")]
    private int $version;

    #[ORM\Column(type: "string", length: 255)]
    private string $event;

    #[ORM\Column(type: "text")]
    private string $payload;

    #[ORM\Column(type: "string", length: 128)]
    private string $createdAt;

    // Getters and setters for each property
    public function getId(): int
    {
        return $this->id;
    }

    public function getStream(): string
    {
        return $this->stream;
    }

    public function setStream(string $stream): void
    {
        $this->stream = $stream;
    }

    public function getAggregateId(): string
    {
        return $this->aggregateId;
    }

    public function setAggregateId(string $aggregateId): void
    {
        $this->aggregateId = $aggregateId;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function setEvent(string $event): void
    {
        $this->event = $event;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): void
    {
        $this->payload = $payload;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
