<?php

declare(strict_types=1);

namespace App\Domain\Chess;

class Board
{
    private array $fields = [
        'a1' => null,
        'b1' => null,
        'c1' => null,
        'd1' => null,
        'e1' => null,
        'f1' => null,
        'g1' => null,
        'h1' => null,
        'a2' => null,
        'b2' => null,
        'c2' => null,
        'd2' => null,
        'e2' => null,
        'f2' => null,
        'g2' => null,
        'h2' => null,
        'a3' => null,
        'b3' => null,
        'c3' => null,
        'd3' => null,
        'e3' => null,
        'f3' => null,
        'g3' => null,
        'h3' => null,
        'a4' => null,
        'b4' => null,
        'c4' => null,
        'd4' => null,
        'e4' => null,
        'f4' => null,
        'g4' => null,
        'h4' => null,
        'a5' => null,
        'b5' => null,
        'c5' => null,
        'd5' => null,
        'e5' => null,
        'f5' => null,
        'g5' => null,
        'h5' => null,
        'a6' => null,
        'b6' => null,
        'c6' => null,
        'd6' => null,
        'e6' => null,
        'f6' => null,
        'g6' => null,
        'h6' => null,
        'a7' => null,
        'b7' => null,
        'c7' => null,
        'd7' => null,
        'e7' => null,
        'f7' => null,
        'g7' => null,
        'h7' => null,
        'a8' => null,
        'b8' => null,
        'c8' => null,
        'd8' => null,
        'e8' => null,
        'f8' => null,
        'g8' => null,
        'h8' => null,
    ];

    public function __construct()
    {
        $this->initializeBoard();
    }

    private function initializePawns(): void
    {
        $charCode = 96;
        for ($i = 0; $i < 8; $i++) {
            $charCode++;
            $this->fields[chr($charCode) . 7] = new Piece(
                Side::BLACK,
                PieceType::PAWN,
                new Position(chr($charCode) . 7)
            );

            $this->fields[chr($charCode) . 2] = new Piece(
                Side::WHITE,
                PieceType::PAWN,
                new Position(chr($charCode) . 2)
            );
        }
    }

    private function initializeBlackPieces(): void
    {
        $this->fields['a8'] = new Piece(Side::BLACK, PieceType::ROOK, new Position('a8'));
        $this->fields['h8'] = new Piece(Side::BLACK, PieceType::ROOK, new Position('h8'));
        $this->fields['b8'] = new Piece(Side::BLACK, PieceType::BISHOP, new Position('b8'));
        $this->fields['g8'] = new Piece(Side::BLACK, PieceType::BISHOP, new Position('g8'));
        $this->fields['c8'] = new Piece(Side::BLACK, PieceType::KNIGHT, new Position('c8'));
        $this->fields['f8'] = new Piece(Side::BLACK, PieceType::KNIGHT, new Position('f8'));
        $this->fields['d8'] = new Piece(Side::BLACK, PieceType::QUEEN, new Position('d8'));
        $this->fields['e8'] = new Piece(Side::BLACK, PieceType::KING, new Position('e8'));
    }

    private function initializeWhitePieces()
    {
        $this->fields['a1'] = new Piece(Side::WHITE, PieceType::ROOK, new Position('a1'));
        $this->fields['h1'] = new Piece(Side::WHITE, PieceType::ROOK, new Position('h1'));
        $this->fields['b1'] = new Piece(Side::WHITE, PieceType::BISHOP, new Position('b1'));
        $this->fields['g1'] = new Piece(Side::WHITE, PieceType::BISHOP, new Position('g1'));
        $this->fields['c1'] = new Piece(Side::WHITE, PieceType::KNIGHT, new Position('c1'));
        $this->fields['f1'] = new Piece(Side::WHITE, PieceType::KNIGHT, new Position('f1'));
        $this->fields['d1'] = new Piece(Side::WHITE, PieceType::QUEEN, new Position('d1'));
        $this->fields['e1'] = new Piece(Side::WHITE, PieceType::KING, new Position('e1'));
    }

    private function initializeBoard()
    {
        $this->initializePawns();
        $this->initializeBlackPieces();
        $this->initializeWhitePieces();
    }

    public function removePiece(Position $position): void
    {
        $this->fields[$position->toString()] = null;
    }

    public function removePieceAtPosition(Position $position): void
    {
        $this->removePiece($position);
    }

    public function placePiece(Piece $piece, Position $position): void
    {
        $this->fields[$position->toString()] = $piece;
    }

    public function getPiece(Position $position): Piece
    {
        if (!$this->fieldHasPiece($position)) {
            throw new \InvalidArgumentException('No piece at the given position.');
        }

        return $this->fields[$position->toString()] ?? null;
    }

    public function fieldHasPiece(Position $position): bool
    {
        return $this->fields[$position->toString()] instanceof Piece;
    }

    public function fieldHasPawn(Position $position): ?Piece
    {
        $piece = $this->fields[$position->toString()];
        return ($piece instanceof Piece && $piece->type === PieceType::PAWN) ? $piece : null;
    }

    public function getNumberOfPieces(?Side $side = null): int
    {
        $pieces = array_filter($this->fields, fn($p) => $p instanceof Piece);
        if ($side === null) {
            return count($pieces);
        }

        return count(
            array_filter(
                $pieces,
                fn(Piece $piece) => $piece->side === $side
            )
        );
    }

    public function movePiece(Piece $piece, Position $to): void
    {
        $this->fields[$to->toString()] = $piece;
        $this->fields[$piece->position->toString()] = null;
        $piece->setPosition($to);
    }

    public function renderBoard()
    {
        $board = '';

        for ($row = 8; $row >= 1; $row--) {
            $board .= " $row ";
            $charCode = 96;
            for ($col = 1; $col <= 8; $col++) {
                $position = new Position(chr($charCode + 1) . $row);

                if ($this->fieldHasPiece($position)) {
                    $board .= $this->getPiece($position)->toSymbol();
                    continue;
                }

                $square = ($row + $col) % 2 === 0 ? Square::WHITE : Square::BLACK;
                $board .= $square->value;
            }

            $board .= PHP_EOL;
        }

        $board .= '    a b c d e f g h';

        return $board;
    }

    public function isPathClear(Position $from, Position $to): bool
    {
        if (!$from->isStraight($to) && !$from->isDiagonal($to)) {
            // Not a straight or diagonal move
            return false;
        }

        [$fileDelta, $rankDelta] = $from->distanceTo($to);

        $fileStep = $fileDelta === 0 ? 0 : ($fileDelta > 0 ? 1 : -1);
        $rankStep = $rankDelta === 0 ? 0 : ($rankDelta > 0 ? 1 : -1);

        $currentFile = $from->fileIndex() + $fileStep;
        $currentRank = $from->rankIndex() + $rankStep;

        $endFile = $to->fileIndex();
        $endRank = $to->rankIndex();

        while ($currentFile !== $endFile || $currentRank !== $endRank) {
            $position = new Position(chr($currentFile + ord('a')) . ($currentRank + 1));
            if ($this->fieldHasPiece($position)) {
                return false;
            }

            $currentFile += $fileStep;
            $currentRank += $rankStep;
        }

        return true;
    }

    public function getKingPosition(Side $side): Position
    {
        foreach ($this->fields as $position => $piece) {
            if ($piece instanceof Piece &&
                $piece->type === PieceType::KING &&
                $piece->side === $side) {
                return Position::fromString($position);
            }
        }

        throw new \RuntimeException("King not found for side {$side->value}");
    }

    public function isSquareAttackedBy(Position $square, Side $bySide): bool
    {
        // If the square is occupied by a piece of the same side, it's not "attacked"
        if ($this->fieldHasPiece($square)) {
            $occupyingPiece = $this->getPiece($square);
            if ($occupyingPiece->side === $bySide) {
                return false;
            }
        }

        // Check if any piece of the given side can attack this square
        foreach ($this->fields as $position => $piece) {
            if (!$piece instanceof Piece || $piece->side !== $bySide) {
                continue;
            }

            $from = Position::fromString($position);

            // Skip if it's the same position
            if ($from->toString() === $square->toString()) {
                continue;
            }

            // Check if this piece can attack the square
            if ($this->canPieceAttackSquare($piece, $from, $square)) {
                return true;
            }
        }

        return false;
    }

    private function canPieceAttackSquare(Piece $piece, Position $from, Position $to): bool
    {
        // Basic piece movement rules (simplified for attack checking)
        switch ($piece->type) {
            case PieceType::PAWN:
                return $this->canPawnAttack($piece->side, $from, $to);
            case PieceType::ROOK:
                return $from->isStraight($to) && $this->isPathClear($from, $to);
            case PieceType::BISHOP:
                return $from->isDiagonal($to) && $this->isPathClear($from, $to);
            case PieceType::QUEEN:
                return ($from->isStraight($to) || $from->isDiagonal($to)) && $this->isPathClear($from, $to);
            case PieceType::KNIGHT:
                return $from->isKnightMove($to);
            case PieceType::KING:
                [$fileDelta, $rankDelta] = $from->distanceTo($to);
                return abs($fileDelta) <= 1 && abs($rankDelta) <= 1;
        }

        return false;
    }

    private function canPawnAttack(Side $side, Position $from, Position $to): bool
    {
        [$fileDelta, $rankDelta] = $from->distanceTo($to);

        if ($side === Side::WHITE) {
            return $rankDelta === 1 && abs($fileDelta) === 1;
        } else {
            return $rankDelta === -1 && abs($fileDelta) === 1;
        }
    }

    public function clone(): Board
    {
        $cloned = new Board();

        // Clear the cloned board and copy pieces
        foreach ($cloned->fields as $position => $value) {
            $cloned->fields[$position] = null;
        }

        foreach ($this->fields as $position => $piece) {
            if ($piece instanceof Piece) {
                $cloned->fields[$position] = new Piece(
                    $piece->side,
                    $piece->type,
                    Position::fromString($position)
                );
            }
        }

        return $cloned;
    }

    /**
     * Get all positions on the board (useful for iterating through all squares).
     *
     * @return Position[]
     */
    public function getAllPositions(): array
    {
        $positions = [];
        for ($rank = 1; $rank <= 8; $rank++) {
            for ($file = 'a'; $file <= 'h'; $file++) {
                $positions[] = new Position($file . $rank);
            }
        }
        return $positions;
    }
}
