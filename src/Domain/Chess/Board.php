<?php

declare(strict_types=1);

namespace App\Domain\Chess;

use App\Domain\Chess\Exception\ChessDomainException;
use App\Domain\Chess\Exception\NoPieceOnSquare;

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

    public function pieceAt(Position $position): ?Piece
    {
        return $this->fields[$position->position];
    }

    public function place(Position $position, Piece $piece): void
    {
        $this->fields[$position->position] = $piece;
    }

    public function remove(Position $position): void
    {
        $this->fields[$position->position] = null;
    }

    public function move(Position $from, Position $to): void
    {
        $piece = $this->pieceAt($from);
        if ($piece === null) {
            throw NoPieceOnSquare::at($from);
        }

        $this->fields[$to->position] = $piece;
        $this->fields[$from->position] = null;
    }

    public function pieceCount(?Side $side = null): int
    {
        $pieces = array_filter(
            $this->fields,
            fn(?Piece $piece) => $piece !== null && ($side === null || $piece->side === $side)
        );

        return count($pieces);
    }

    public function kingPosition(Side $side): Position
    {
        foreach ($this->fields as $position => $piece) {
            if ($piece !== null && $piece->type === PieceType::KING && $piece->side === $side) {
                return Position::fromString($position);
            }
        }

        throw new ChessDomainException("King not found for side {$side->value}");
    }

    public function isPathClear(Position $from, Position $to): bool
    {
        if (!$from->isStraight($to) && !$from->isDiagonal($to)) {
            // Not a straight or diagonal move
            return false;
        }

        $fileStep = $from->fileDistanceTo($to) <=> 0;
        $rankStep = $from->rankDistanceTo($to) <=> 0;

        $currentFile = $from->fileIndex() + $fileStep;
        $currentRank = $from->rankIndex() + $rankStep;

        while ($currentFile !== $to->fileIndex() || $currentRank !== $to->rankIndex()) {
            $position = new Position(chr($currentFile + ord('a')) . ($currentRank + 1));
            if ($this->pieceAt($position) !== null) {
                return false;
            }

            $currentFile += $fileStep;
            $currentRank += $rankStep;
        }

        return true;
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

            $fields[chr(ord('a') + $fileIndex) . $rank] = self::pieceFromFenChar($char);
            $fileIndex++;
        }
    }

    private static function pieceFromFenChar(string $char): Piece
    {
        $type = match (strtoupper($char)) {
            'P' => PieceType::PAWN,
            'N' => PieceType::KNIGHT,
            'B' => PieceType::BISHOP,
            'R' => PieceType::ROOK,
            'Q' => PieceType::QUEEN,
            'K' => PieceType::KING,
            default => throw new ChessDomainException('Invalid FEN piece character: ' . $char),
        };
        $side = ctype_upper($char) ? Side::WHITE : Side::BLACK;

        return new Piece($type, $side);
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
        foreach (Position::all() as $position) {
            $fields[$position->position] = null;
        }

        return $fields;
    }
}
