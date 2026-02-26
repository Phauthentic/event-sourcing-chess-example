<?php

declare(strict_types=1);

namespace App\Domain\Chess\Specification;

use App\Domain\Chess\Board;
use App\Domain\Chess\Piece;
use App\Domain\Chess\PieceType;
use App\Domain\Chess\Position;

/**
 * Validates king movement rules.
 * - Move exactly 1 square in any direction (including diagonally)
 * - Castling is handled separately in MoveValidator
 */
class KingMovementSpecification implements PieceMovementSpecification
{
    public function isSatisfiedBy(Piece $piece, Position $from, Position $to, Board $board): bool
    {
        if ($piece->type !== PieceType::KING) {
            return false;
        }

        // Kings move exactly 1 square in any direction
        [$fileDelta, $rankDelta] = $from->distanceTo($to);

        return abs($fileDelta) <= 1 && abs($rankDelta) <= 1 && ($fileDelta !== 0 || $rankDelta !== 0);
    }
}