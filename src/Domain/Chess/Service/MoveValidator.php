<?php

declare(strict_types=1);

namespace App\Domain\Chess\Service;

use App\Domain\Chess\Board;
use App\Domain\Chess\CastlingSide;
use App\Domain\Chess\Exception\NoPieceOnSquare;
use App\Domain\Chess\Game;
use App\Domain\Chess\MoveKind;
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
    /**
     * @var array<string, PieceMovementSpecification>
     */
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
        $board = $game->board();
        $piece = $board->pieceAt($from) ?? throw NoPieceOnSquare::at($from);

        if (!$this->isBasicMoveValid($game, $to, $piece)) {
            return false;
        }

        return match ($this->moveKind($board, $from, $to, $game->enPassantTarget())) {
            MoveKind::CASTLING => $this->isCastlingLegal($game, $from, $to),
            MoveKind::EN_PASSANT => true, // conditions are fully checked by the classifier
            MoveKind::STANDARD => $this->isStandardMoveLegal($game, $piece, $from, $to, $promotion),
        };
    }

    /**
     * Classifies a move so that validation and execution share one definition
     * of what counts as castling or an en passant capture.
     */
    public function moveKind(Board $board, Position $from, Position $to, ?Position $enPassantTarget): MoveKind
    {
        $piece = $board->pieceAt($from) ?? throw NoPieceOnSquare::at($from);

        return match (true) {
            $this->isCastlingMove($piece, $from, $to) => MoveKind::CASTLING,
            $this->isEnPassantCapture($board, $piece, $from, $to, $enPassantTarget) => MoveKind::EN_PASSANT,
            default => MoveKind::STANDARD,
        };
    }

    /**
     * Checks whether any piece of the given side attacks the given square.
     */
    public function isSquareAttackedBy(Board $board, Position $square, Side $bySide): bool
    {
        if ($board->pieceAt($square)?->side === $bySide) {
            return false;
        }

        foreach (Position::all() as $from) {
            if ($this->attacksSquareFrom($board, $from, $square, $bySide)) {
                return true;
            }
        }

        return false;
    }

    private function attacksSquareFrom(Board $board, Position $from, Position $square, Side $bySide): bool
    {
        $piece = $board->pieceAt($from);
        if ($piece === null || $piece->side !== $bySide || $from->position === $square->position) {
            return false;
        }

        // Pawns attack diagonally only; every other piece attacks the way it moves.
        if ($piece->type === PieceType::PAWN) {
            return $this->isPawnAttack($piece->side, $from, $square);
        }

        return $this->specifications[$piece->type->value]->isSatisfiedBy($piece, $from, $square, $board);
    }

    private function isPawnAttack(Side $side, Position $from, Position $to): bool
    {
        $forward = $side === Side::WHITE ? 1 : -1;

        return $from->rankDistanceTo($to) === $forward && abs($from->fileDistanceTo($to)) === 1;
    }

    private function isStandardMoveLegal(Game $game, Piece $piece, Position $from, Position $to, ?PieceType $promotion): bool
    {
        if (!$this->specifications[$piece->type->value]->isSatisfiedBy($piece, $from, $to, $game->board())) {
            return false;
        }

        if ($promotion !== null && !$this->isPromotionValid($piece, $to, $promotion)) {
            return false;
        }

        return !$this->wouldLeaveKingInCheck($game, $from, $to);
    }

    private function isBasicMoveValid(Game $game, Position $to, Piece $piece): bool
    {
        // Must be the active player's piece
        if ($game->activePlayer()->side !== $piece->side) {
            return false;
        }

        // Destination must be empty or contain an enemy piece
        return $game->board()->pieceAt($to)?->side !== $piece->side;
    }

    private function isEnPassantCapture(
        Board $board,
        Piece $piece,
        Position $from,
        Position $to,
        ?Position $enPassantTarget
    ): bool {
        if ($piece->type !== PieceType::PAWN || $enPassantTarget === null) {
            return false;
        }

        if ($to->position !== $enPassantTarget->position || $board->pieceAt($to) !== null) {
            return false;
        }

        return $this->isPawnAttack($piece->side, $from, $to);
    }

    private function isCastlingMove(Piece $piece, Position $from, Position $to): bool
    {
        if ($piece->type !== PieceType::KING) {
            return false;
        }

        return $from->rankDistanceTo($to) === 0 && abs($from->fileDistanceTo($to)) === 2;
    }

    private function isCastlingLegal(Game $game, Position $from, Position $to): bool
    {
        $board = $game->board();
        $piece = $board->pieceAt($from) ?? throw NoPieceOnSquare::at($from);
        $opponentSide = $piece->side->opponent();

        $isKingside = $to->fileIndex() > $from->fileIndex();
        $castlingSide = $isKingside ? CastlingSide::KINGSIDE : CastlingSide::QUEENSIDE;
        if (!$game->castlingRights()->hasRights($piece->side, $castlingSide)) {
            return false;
        }

        // King must not be in check, nor pass through an attacked square
        foreach ([$from, ...$this->castlingKingPath($from, $isKingside)] as $position) {
            if ($this->isSquareAttackedBy($board, $position, $opponentSide)) {
                return false;
            }
        }

        // Squares between king and rook must be empty
        $rookPosition = new Position(($isKingside ? 'h' : 'a') . $from->rank());

        return $board->isPathClear($from, $rookPosition);
    }

    /**
     * @return array<int, Position>
     */
    private function castlingKingPath(Position $kingFrom, bool $isKingside): array
    {
        $rank = $kingFrom->rank();

        return match ($isKingside) {
            // Kingside: e1-f1-g1 (for white) or e8-f8-g8 (for black)
            true => [
                new Position('f' . $rank),
                new Position('g' . $rank),
            ],
            // Queenside: e1-d1-c1 (for white) or e8-d8-c8 (for black)
            false => [
                new Position('d' . $rank),
                new Position('c' . $rank),
            ],
        };
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
        return !in_array($promotion, [PieceType::KING, PieceType::PAWN], true);
    }

    private function wouldLeaveKingInCheck(Game $game, Position $from, Position $to): bool
    {
        $piece = $game->board()->pieceAt($from) ?? throw NoPieceOnSquare::at($from);

        // Simulate on a copy only — the live board must not be mutated.
        $simulatedBoard = clone $game->board();
        $simulatedBoard->move($from, $to);

        return $this->isSquareAttackedBy(
            $simulatedBoard,
            $simulatedBoard->kingPosition($piece->side),
            $piece->side->opponent()
        );
    }
}
