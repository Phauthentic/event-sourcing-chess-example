<?php

declare(strict_types=1);

namespace App\Domain\Chess\Specification;

use App\Domain\Chess\Board;
use App\Domain\Chess\Piece;
use App\Domain\Chess\PieceType;
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
        if ($piece->type !== PieceType::PAWN) {
            return false;
        }

        [$fileDelta, $rankDelta] = $from->distanceTo($to);

        if ($piece->side === Side::WHITE) {
            return $this->isValidWhitePawnMove($fileDelta, $rankDelta, $from, $to, $board);
        } else {
            return $this->isValidBlackPawnMove($fileDelta, $rankDelta, $from, $to, $board);
        }
    }

    private function isValidWhitePawnMove(int $fileDelta, int $rankDelta, Position $from, Position $to, Board $board): bool
    {
        // White pawns move "up" (increasing rank)

        // Forward movement (no capture)
        if ($fileDelta === 0 && !$board->fieldHasPiece($to)) {
            // 1 square forward
            if ($rankDelta === 1) {
                return true;
            }
            // 2 squares forward from starting position (rank 2)
            if ($rankDelta === 2 && $from->rank() === 2) {
                // Check that the intermediate square is also empty
                $intermediate = new Position($from->file() . '3');
                return !$board->fieldHasPiece($intermediate);
            }
        }

        // Diagonal capture
        if (abs($fileDelta) === 1 && $rankDelta === 1 && $board->fieldHasPiece($to)) {
            return true;
        }

        return false;
    }

    private function isValidBlackPawnMove(int $fileDelta, int $rankDelta, Position $from, Position $to, Board $board): bool
    {
        // Black pawns move "down" (decreasing rank)

        // Forward movement (no capture)
        if ($fileDelta === 0 && !$board->fieldHasPiece($to)) {
            // 1 square forward
            if ($rankDelta === -1) {
                return true;
            }
            // 2 squares forward from starting position (rank 7)
            if ($rankDelta === -2 && $from->rank() === 7) {
                // Check that the intermediate square is also empty
                $intermediate = new Position($from->file() . '6');
                return !$board->fieldHasPiece($intermediate);
            }
        }

        // Diagonal capture
        if (abs($fileDelta) === 1 && $rankDelta === -1) {
            return $board->fieldHasPiece($to);
        }

        return false;
    }
}