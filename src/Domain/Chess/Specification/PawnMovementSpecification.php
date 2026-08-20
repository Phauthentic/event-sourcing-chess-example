<?php

declare(strict_types=1);

namespace App\Domain\Chess\Specification;

use App\Domain\Chess\Board;
use App\Domain\Chess\Piece;
use App\Domain\Chess\Position;
use App\Domain\Chess\Side;

/**
 * Validates pawn movement rules.
 * - Forward: 1 square always, 2 squares from starting position
 * - Capture: diagonally 1 square forward
 * - En passant: handled separately in MoveValidator
 */
class PawnMovementSpecification implements PieceMovementSpecification
{
    public function isSatisfiedBy(Piece $piece, Position $from, Position $to, Board $board): bool
    {
        $forward = $piece->side === Side::WHITE ? 1 : -1;
        $startRank = $piece->side === Side::WHITE ? 2 : 7;

        $fileDelta = $from->fileDistanceTo($to);
        $rankDelta = $from->rankDistanceTo($to);

        // Diagonal capture
        if (abs($fileDelta) === 1) {
            return $rankDelta === $forward && $board->pieceAt($to) !== null;
        }

        // Forward movement (no capture)
        if ($fileDelta !== 0 || $board->pieceAt($to) !== null) {
            return false;
        }

        if ($rankDelta === $forward) {
            return true;
        }

        // Double step from the starting rank, intermediate square must be free
        return $rankDelta === 2 * $forward
            && $from->rank() === $startRank
            && $board->pieceAt(new Position($from->file() . ($startRank + $forward))) === null;
    }
}
