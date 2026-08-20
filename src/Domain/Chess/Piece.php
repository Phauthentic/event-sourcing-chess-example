<?php

declare(strict_types=1);

namespace App\Domain\Chess;

/**
 *
 */
class Piece
{
    public function __construct(
        public readonly Side $side,
        public PieceType $type,
        public Position $position
    ) {
    }

    public function __toString()
    {
        return $this->type->value;
    }

    public function promote(PieceType $pieceType): void
    {
        if ($this->type !== PieceType::PAWN) {
            throw new \InvalidArgumentException('Only pawns can be promoted.');
        }

        $this->type = $pieceType;
    }

    public function setPosition(Position $position): void
    {
        $this->position = $position;
    }
}
