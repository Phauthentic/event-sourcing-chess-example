<?php

declare(strict_types=1);

namespace App\Domain\Chess;

/**
 *
 */
class Move
{
    public function __construct(
        public readonly string $from,
        public readonly string $to
    ) {
        $this->assertValidMove($from . ' - ' . $to);
    }

    public static function fromString(string $move): self
    {
        static::assertValidMove($move);

        return new self(...explode(' - ', $move));
    }

    private static function assertValidMove(string $move): void
    {
        if (!preg_match('/^([a-h][1-8])\s*-\s*([a-h][1-8])$/', $move, $matches)) {
            throw new \InvalidArgumentException('Invalid move');
        }
    }
}
