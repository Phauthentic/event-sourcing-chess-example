<?php

declare(strict_types=1);

namespace App\Domain\Chess;

/**
 *
 */
class Position
{
    public function __construct(
        public readonly string $position,
    ) {
        $this->assertPositionIsInRange($position);
    }

    private function assertPositionIsInRange($position): void
    {
        if (!preg_match('/^([a-h][1-8])$/', $position, $matches)) {
            throw new \InvalidArgumentException('Invalid position: ' . $position);
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

    public function toString()
    {
        return $this->position;
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

    public function distanceTo(Position $other): array
    {
        return [
            $other->fileIndex() - $this->fileIndex(),
            $other->rankIndex() - $this->rankIndex(),
        ];
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
        [$fileDelta, $rankDelta] = $this->distanceTo($other);
        return abs($fileDelta) === abs($rankDelta) && $fileDelta !== 0;
    }

    public function isStraight(Position $other): bool
    {
        return $this->isSameFile($other) || $this->isSameRank($other);
    }

    public function isKnightMove(Position $other): bool
    {
        [$fileDelta, $rankDelta] = $this->distanceTo($other);
        $fileAbs = abs($fileDelta);
        $rankAbs = abs($rankDelta);

        return ($fileAbs === 2 && $rankAbs === 1) || ($fileAbs === 1 && $rankAbs === 2);
    }
}
