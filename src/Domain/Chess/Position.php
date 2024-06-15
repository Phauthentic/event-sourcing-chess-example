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
        $this->assertValidField($position);
    }

    public function assertValidField(string $position): void
    {
        if (!preg_match('/^([a-h][1-8])\s*-\s*([a-h][1-8])$/', $position )) {
            //throw new ChessDomainException('Invalid position');
        }
    }

    public function __toString(): string
    {
        return $this->position;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->position;
    }
}
