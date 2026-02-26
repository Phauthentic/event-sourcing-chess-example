<?php

declare(strict_types=1);

namespace App\Domain\Chess\Specification;

use App\Domain\Chess\Board;
use App\Domain\Chess\Piece;
use App\Domain\Chess\PieceType;
use App\Domain\Chess\Position;

/**
 * Validates rook movement rules.
 * - Move horizontally or vertically any number of squares
 * - Path must be clear (no pieces blocking)
 */
class RookMovementSpecification implements PieceMovementSpecification
{
    public function isSatisfiedBy(Piece $piece, Position $from, Position $to, Board $board): bool
    {
        if ($piece->type !== PieceType::ROOK) {
            return false;
        }

        // Rooks move horizontally or vertically
        if (!$from->isStraight($to)) {
            return false;
        }

        // Path must be clear
        return $board->isPathClear($from, $to);
    }
}