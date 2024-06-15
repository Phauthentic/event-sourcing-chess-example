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
    )
    {
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->type->value;
    }

    /**
     * Each piece type (other than pawns) is identified by an uppercase letter. English-speaking players use the
     * letters K for king, Q for queen, R for rook, B for bishop, and N for knight. Different initial letters are
     * used by other languages.
     *
     * In modern chess literature, especially that intended for an international audience, the language-specific
     * letters are usually replaced by universally recognized piece symbols; for example, ♞c6 in place of Nc6.
     * This style is known as figurine algebraic notation. The Unicode Miscellaneous Symbols set includes all
     * the symbols necessary for figurine algebraic notation.[2]
     *
     * @link https://en.wikipedia.org/wiki/Algebraic_notation_(chess)#Naming_the_pieces
     * @return string
     */
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

    /**
     * When a pawn reaches the end of the board it “promotes”, it turns into another piece. Most of the time players
     * promote to a queen, but a rook, knight, or bishop is also possible.
     *
     * @link https://en.wikipedia.org/wiki/Promotion_(chess)
     * @param PieceType $pieceType
     */
    public function promote(PieceType $pieceType): void
    {
        $this->type = $pieceType;
    }
}
