<?php

declare(strict_types=1);

namespace App\Domain\Chess\Specification;

use App\Domain\Chess\Board;
use App\Domain\Chess\Piece;
use App\Domain\Chess\PieceType;
use App\Domain\Chess\Position;

/**
 * Validates knight movement rules.
 * - Move in L-shape: 2 squares in one direction, 1 square perpendicular
 * - Can jump over pieces (no path clearance check needed)
 */
class KnightMovementSpecification implements PieceMovementSpecification
{
    public function isSatisfiedBy(Piece $piece, Position $from, Position $to, Board $board): bool
    {
        if ($piece->type !== PieceType::KNIGHT) {
            return false;
        }

        // Knights move in L-shape (2+1)
        return $from->isKnightMove($to);
    }
}