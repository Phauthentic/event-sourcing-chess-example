<?php

declare(strict_types=1);

namespace App\Domain\Chess;

use App\Domain\Chess\Exception\InvalidPosition;

class Position
{
    public function __construct(
        public readonly string $position,
    ) {
        if (!preg_match('/^[a-h][1-8]$/', $position)) {
            throw InvalidPosition::fromString($position);
        }
    }

    public function __toString(): string
    {
        return $this->position;
    }

    public static function fromString(string $position): self
    {
        return new self($position);
    }

    public function file(): string
    {
        return $this->position[0];
    }

    public function rank(): int
    {
        return (int) $this->position[1];
    }

    public function fileIndex(): int
    {
        return ord($this->file()) - ord('a');
    }

    public function rankIndex(): int
    {
        return $this->rank() - 1;
    }

    public function fileDistanceTo(Position $other): int
    {
        return $other->fileIndex() - $this->fileIndex();
    }

    public function rankDistanceTo(Position $other): int
    {
        return $other->rankIndex() - $this->rankIndex();
    }

    public function isSameFile(Position $other): bool
    {
        return $this->file() === $other->file();
    }

    public function isSameRank(Position $other): bool
    {
        return $this->rank() === $other->rank();
    }

    public function isDiagonal(Position $other): bool
    {
        $fileDelta = $this->fileDistanceTo($other);

        return $fileDelta !== 0 && abs($fileDelta) === abs($this->rankDistanceTo($other));
    }

    public function isStraight(Position $other): bool
    {
        return $this->isSameFile($other) || $this->isSameRank($other);
    }

    public function isKnightMove(Position $other): bool
    {
        $fileAbs = abs($this->fileDistanceTo($other));
        $rankAbs = abs($this->rankDistanceTo($other));

        return ($fileAbs === 2 && $rankAbs === 1) || ($fileAbs === 1 && $rankAbs === 2);
    }

    /**
     * All 64 board positions, a1 through h8.
     *
     * @return \Generator<int, Position>
     */
    public static function all(): \Generator
    {
        for ($rank = 1; $rank <= 8; $rank++) {
            foreach (['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'] as $file) {
                yield new self($file . $rank);
            }
        }
    }
}
