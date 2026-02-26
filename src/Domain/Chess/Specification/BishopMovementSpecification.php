<?php

declare(strict_types=1);

namespace App\Domain\Chess\Specification;

use App\Domain\Chess\Board;
use App\Domain\Chess\Piece;
use App\Domain\Chess\PieceType;
use App\Domain\Chess\Position;

/**
 * Validates bishop movement rules.
 * - Move diagonally any number of squares
 * - Path must be clear (no pieces blocking)
 */
class BishopMovementSpecification implements PieceMovementSpecification
{
    public function isSatisfiedBy(Piece $piece, Position $from, Position $to, Board $board): bool
    {
        if ($piece->type !== PieceType::BISHOP) {
            return false;
        }

        // Bishops move diagonally
        if (!$from->isDiagonal($to)) {
            return false;
        }

        // Path must be clear
        return $board->isPathClear($from, $to);
    }
}