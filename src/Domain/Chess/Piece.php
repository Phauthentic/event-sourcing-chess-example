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
        public readonly PieceType $type,
        public Position $position
    ) {
    }

    public function __toString()
    {
        return $this->type->value;
    }

    public function toSymbol(): string
    {
        $isBlack = $this->side === Side::BLACK;

        switch ($this->type) {
            case PieceType::PAWN:
                return $isBlack ? '♟' : '♙';
            case PieceType::QUEEN:
                return $isBlack ? '♛' : '♕';
            case PieceType::ROOK:
                return $isBlack ? '♜' : '♖';
            case PieceType::BISHOP:
                return $isBlack ? '♝' : '♗';
            case PieceType::KNIGHT:
                return $isBlack ? '♞' : '♘';
            case PieceType::KING:
                return $isBlack ? '♚' : '♔';
            default:
                throw new \InvalidArgumentException('Invalid PieceType provided.');
        }
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
