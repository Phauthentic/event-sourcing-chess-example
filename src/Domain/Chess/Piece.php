<?php

declare(strict_types=1);

namespace App\Domain\Chess;

final readonly class Piece
{
    public function __construct(
        public PieceType $type,
        public Side $side
    ) {
    }

    public function __toString(): string
    {
        return $this->type->value;
    }
}
