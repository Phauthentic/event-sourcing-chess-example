<?php

declare(strict_types=1);

namespace App\Domain\Chess;

/**
 *
 */
class Position
{
    public function __construct(
        public readonly string $position,
    ) {
        $this->assertPositionIsInRange($position);
    }

    private function assertPositionIsInRange($position): void
    {
        if (!preg_match('/^([a-h][1-8])$/', $position, $matches)) {
            throw new \InvalidArgumentException('Invalid position: ' . $position);
        }
    }

    public function __toString(): string
    {
        return $this->position;
    }

    public static function fromString(string $position): self
    {
        return new self($position);
    }

    public function toString()
    {
        return $this->position;
    }
}
