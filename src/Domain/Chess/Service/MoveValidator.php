<?php

declare(strict_types=1);

namespace App\Domain\Chess\Service;

use App\Domain\Chess\Board;
use App\Domain\Chess\CastlingRights;
use App\Domain\Chess\Game;
use App\Domain\Chess\Piece;
use App\Domain\Chess\PieceType;
use App\Domain\Chess\Position;
use App\Domain\Chess\Side;
use App\Domain\Chess\Specification\BishopMovementSpecification;
use App\Domain\Chess\Specification\KingMovementSpecification;
use App\Domain\Chess\Specification\KnightMovementSpecification;
use App\Domain\Chess\Specification\PawnMovementSpecification;
use App\Domain\Chess\Specification\PieceMovementSpecification;
use App\Domain\Chess\Specification\QueenMovementSpecification;
use App\Domain\Chess\Specification\RookMovementSpecification;

/**
 * Domain service for validating chess moves.
 * Orchestrates piece movement specifications and handles special rules.
 */
class MoveValidator
{
    private array $specifications;

    public function __construct()
    {
        $this->specifications = [
            PieceType::PAWN->value => new PawnMovementSpecification(),
            PieceType::ROOK->value => new RookMovementSpecification(),
            PieceType::BISHOP->value => new BishopMovementSpecification(),
            PieceType::KNIGHT->value => new KnightMovementSpecification(),
            PieceType::QUEEN->value => new QueenMovementSpecification(),
            PieceType::KING->value => new KingMovementSpecification(),
        ];
    }

    public function isMoveLegal(
        Game $game,
        Position $from,
        Position $to,
        ?PieceType $promotion = null
    ): bool {
        $board = $game->getBoard();
        $piece = $board->getPiece($from);

        // 1. Basic validations
        if (!$this->isBasicMoveValid($game, $from, $to, $piece)) {
            return false;
        }

        // 2. Check if it's a special move (castling, en passant)
        if ($this->isCastlingMove($piece, $from, $to)) {
            return $this->isCastlingLegal($game, $from, $to);
        }

        if ($this->isEnPassantCapture($game, $piece, $from, $to)) {
            return true; // En passant is valid if the conditions are met
        }

        // 3. Piece-specific movement validation
        $specification = $this->specifications[$piece->type->value];
        if (!$specification->isSatisfiedBy($piece, $from, $to, $board)) {
            return false;
        }

        // 4. Check promotion rules
        if ($promotion !== null && !$this->isPromotionValid($piece, $to, $promotion)) {
            return false;
        }

        // 5. Simulate the move and check if it leaves king in check
        return !$this->wouldLeaveKingInCheck($game, $from, $to);
    }

    private function isBasicMoveValid(Game $game, Position $from, Position $to, Piece $piece): bool
    {
        // Must be the active player's piece
        if ($game->getActivePlayer()->side !== $piece->side) {
            return false;
        }

        // Destination must be empty or contain enemy piece (unless en passant)
        if ($game->getBoard()->fieldHasPiece($to)) {
            $targetPiece = $game->getBoard()->getPiece($to);
            if ($targetPiece->side === $piece->side) {
                return false; // Can't capture own piece
            }
        } elseif (!$this->isEnPassantCapture($game, $piece, $from, $to)) {
            // If destination is empty and it's not en passant, this is just a regular move
            // The piece-specific validation will handle whether this is valid
        }

        return true;
    }

    private function isEnPassantCapture(Game $game, Piece $piece, Position $from, Position $to): bool
    {
        if ($piece->type !== PieceType::PAWN) {
            return false;
        }

        // Must be moving diagonally to an empty square
        [$fileDelta, $rankDelta] = $from->distanceTo($to);
        if (abs($fileDelta) !== 1) {
            return false;
        }

        $expectedRankDelta = $piece->side === Side::WHITE ? 1 : -1;
        if ($rankDelta !== $expectedRankDelta) {
            return false;
        }

        // Destination must be empty (regular capture check)
        if ($game->getBoard()->fieldHasPiece($to)) {
            return false;
        }

        // Must match the en passant target
        $enPassantTarget = $game->getEnPassantTarget();
        if ($enPassantTarget === null) {
            return false;
        }

        return $to->toString() === $enPassantTarget->toString();
    }

    private function isCastlingMove(Piece $piece, Position $from, Position $to): bool
    {
        if ($piece->type !== PieceType::KING) {
            return false;
        }

        [$fileDelta, $rankDelta] = $from->distanceTo($to);

        // Castling: king moves 2 squares horizontally
        return $rankDelta === 0 && abs($fileDelta) === 2;
    }

    private function isCastlingLegal(Game $game, Position $from, Position $to): bool
    {
        $board = $game->getBoard();
        $piece = $board->getPiece($from);
        $castlingRights = $game->getCastlingRights();

        // Determine castling type
        $isKingside = $to->fileIndex() > $from->fileIndex();
        $type = $isKingside ? 'kingside' : 'queenside';

        // Check castling rights
        if (!$castlingRights->hasRights($piece->side, $type)) {
            return false;
        }

        // King must not be in check
        if ($board->isSquareAttackedBy($from, $this->getOpponentSide($piece->side))) {
            return false;
        }

        // Squares king passes through must not be attacked
        $rookFile = $isKingside ? 'h' : 'a';
        $kingPathPositions = $this->getCastlingKingPath($from, $isKingside);

        foreach ($kingPathPositions as $position) {
            if ($board->isSquareAttackedBy($position, $this->getOpponentSide($piece->side))) {
                return false;
            }
        }

        // Squares between king and rook must be empty
        $rookPosition = new Position($rookFile . $from->rank());
        $betweenPositions = $this->getPositionsBetween($from, $rookPosition);

        foreach ($betweenPositions as $position) {
            if ($board->fieldHasPiece($position)) {
                return false;
            }
        }

        return true;
    }

    private function getCastlingKingPath(Position $kingFrom, bool $isKingside): array
    {
        $rank = $kingFrom->rank();
        if ($isKingside) {
            // Kingside: e1-f1-g1 (for white) or e8-f8-g8 (for black)
            return [
                new Position('f' . $rank),
                new Position('g' . $rank),
            ];
        } else {
            // Queenside: e1-d1-c1 (for white) or e8-d8-c8 (for black)
            return [
                new Position('d' . $rank),
                new Position('c' . $rank),
            ];
        }
    }

    private function getPositionsBetween(Position $from, Position $to): array
    {
        $positions = [];
        [$fileDelta, $rankDelta] = $from->distanceTo($to);

        $fileStep = $fileDelta === 0 ? 0 : ($fileDelta > 0 ? 1 : -1);
        $rankStep = $rankDelta === 0 ? 0 : ($rankDelta > 0 ? 1 : -1);

        $currentFile = $from->fileIndex() + $fileStep;
        $currentRank = $from->rankIndex() + $rankStep;

        $endFile = $to->fileIndex();
        $endRank = $to->rankIndex();

        while ($currentFile !== $endFile || $currentRank !== $endRank) {
            $positions[] = new Position(chr($currentFile + ord('a')) . ($currentRank + 1));
            $currentFile += $fileStep;
            $currentRank += $rankStep;
        }

        return $positions;
    }

    private function isPromotionValid(Piece $piece, Position $to, PieceType $promotion): bool
    {
        if ($piece->type !== PieceType::PAWN) {
            return false;
        }

        // Promotion only on 8th rank for white, 1st rank for black
        $targetRank = $piece->side === Side::WHITE ? 8 : 1;
        if ($to->rank() !== $targetRank) {
            return false;
        }

        // Can't promote to king or pawn
        return !in_array($promotion, [PieceType::KING, PieceType::PAWN]);
    }

    private function wouldLeaveKingInCheck(Game $game, Position $from, Position $to): bool
    {
        $board = $game->getBoard();
        $piece = $board->getPiece($from);

        // Clone board and simulate the move
        $simulatedBoard = $board->clone();
        $simulatedBoard->movePiece($piece, $to);

        // Remove captured piece if any
        if ($board->fieldHasPiece($to)) {
            $simulatedBoard->removePiece($to);
        }

        // Check if our king is now under attack
        $kingPosition = $simulatedBoard->getKingPosition($piece->side);
        $opponentSide = $this->getOpponentSide($piece->side);

        return $simulatedBoard->isSquareAttackedBy($kingPosition, $opponentSide);
    }

    private function getOpponentSide(Side $side): Side
    {
        return $side === Side::WHITE ? Side::BLACK : Side::WHITE;
    }
}