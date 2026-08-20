<?php

declare(strict_types=1);

namespace App\Domain\Chess;

class Board
{
    public const START_FEN = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR';

    private const FILES = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];

    /**
     * @var array<string, Piece|null>
     */
    private array $fields;

    public function __construct()
    {
        $this->fields = self::fieldsFromFen(self::START_FEN);
    }

    public static function fromFen(string $fen): self
    {
        $board = new self();
        $board->fields = self::fieldsFromFen($fen);

        return $board;
    }

    public function toFen(): string
    {
        $ranks = [];
        for ($rank = 8; $rank >= 1; $rank--) {
            $ranks[] = $this->rankToFen($rank);
        }

        return implode('/', $ranks);
    }

    private function rankToFen(int $rank): string
    {
        $fen = '';
        $emptySquares = 0;
        foreach (self::FILES as $file) {
            $piece = $this->fields[$file . $rank];
            if ($piece === null) {
                $emptySquares++;
                continue;
            }

            $fen .= ($emptySquares > 0 ? $emptySquares : '') . self::pieceToFenChar($piece);
            $emptySquares = 0;
        }

        return $fen . ($emptySquares > 0 ? $emptySquares : '');
    }

    /**
     * @return array<string, Piece|null>
     */
    private static function fieldsFromFen(string $fen): array
    {
        $fields = self::emptyFields();
        $rank = 8;
        foreach (explode('/', $fen) as $rankFen) {
            self::fillRankFromFen($fields, $rankFen, $rank);
            $rank--;
        }

        return $fields;
    }

    /**
     * @param array<string, Piece|null> $fields
     */
    private static function fillRankFromFen(array &$fields, string $rankFen, int $rank): void
    {
        $fileIndex = 0;
        foreach (str_split($rankFen) as $char) {
            if (ctype_digit($char)) {
                $fileIndex += (int) $char;
                continue;
            }

            $position = new Position(chr(ord('a') + $fileIndex) . $rank);
            $fields[$position->position] = self::pieceFromFenChar($char, $position);
            $fileIndex++;
        }
    }

    private static function pieceFromFenChar(string $char, Position $position): Piece
    {
        $type = match (strtoupper($char)) {
            'P' => PieceType::PAWN,
            'N' => PieceType::KNIGHT,
            'B' => PieceType::BISHOP,
            'R' => PieceType::ROOK,
            'Q' => PieceType::QUEEN,
            'K' => PieceType::KING,
            default => throw new \InvalidArgumentException('Invalid FEN piece character: ' . $char),
        };
        $side = ctype_upper($char) ? Side::WHITE : Side::BLACK;

        return new Piece($side, $type, $position);
    }

    private static function pieceToFenChar(Piece $piece): string
    {
        $char = strtoupper($piece->type->value);

        return $piece->side === Side::WHITE ? $char : strtolower($char);
    }

    /**
     * @return array<string, Piece|null>
     */
    private static function emptyFields(): array
    {
        $fields = [];
        for ($rank = 1; $rank <= 8; $rank++) {
            foreach (self::FILES as $file) {
                $fields[$file . $rank] = null;
            }
        }

        return $fields;
    }

    public function removePiece(Position $position): void
    {
        $this->fields[$position->position] = null;
    }

    public function placePiece(Piece $piece, Position $position): void
    {
        $this->fields[$position->position] = $piece;
    }

    public function getPiece(Position $position): Piece
    {
        $piece = $this->fields[$position->position];
        if (!$piece instanceof Piece) {
            throw new \InvalidArgumentException('No piece at the given position.');
        }

        return $piece;
    }

    public function fieldHasPiece(Position $position): bool
    {
        return $this->fields[$position->position] instanceof Piece;
    }

    public function fieldHasPawn(Position $position): ?Piece
    {
        $piece = $this->fields[$position->position];
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
        $this->fields[$to->position] = $piece;
        $this->fields[$piece->position->position] = null;
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
            if ($from->position === $square->position) {
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
        }

        return $rankDelta === -1 && abs($fileDelta) === 1;
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
