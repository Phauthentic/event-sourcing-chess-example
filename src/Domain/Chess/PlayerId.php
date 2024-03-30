<?php

declare(strict_types=1);

namespace App\Domain\Chess;

/**
 *
 */
class PlayerId
{
    public function __construct(
        public readonly string $id
    )
    {
    }

    public function __toString(): string
    {
        return $this->id;
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }

    public function sameAs(PlayerId $other): bool
    {
        return $this->id === $other->id;
    }
}
