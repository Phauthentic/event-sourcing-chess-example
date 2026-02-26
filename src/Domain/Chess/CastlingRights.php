<?php

declare(strict_types=1);

namespace App\Domain\Chess;

/**
 * Represents castling rights for both players.
 * Immutable value object.
 */
class CastlingRights
{
    public function __construct(
        public readonly bool $whiteKingside,
        public readonly bool $whiteQueenside,
        public readonly bool $blackKingside,
        public readonly bool $blackQueenside,
    ) {}

    public static function initial(): self
    {
        return new self(true, true, true, true);
    }

    public function revokeForSide(Side $side, string $type): self
    {
        return match ($side) {
            Side::WHITE => match ($type) {
                'kingside' => new self(false, $this->whiteQueenside, $this->blackKingside, $this->blackQueenside),
                'queenside' => new self($this->whiteKingside, false, $this->blackKingside, $this->blackQueenside),
                'both' => new self(false, false, $this->blackKingside, $this->blackQueenside),
                default => $this,
            },
            Side::BLACK => match ($type) {
                'kingside' => new self($this->whiteKingside, $this->whiteQueenside, false, $this->blackQueenside),
                'queenside' => new self($this->whiteKingside, $this->whiteQueenside, $this->blackKingside, false),
                'both' => new self($this->whiteKingside, $this->whiteQueenside, false, false),
                default => $this,
            },
        };
    }

    public function hasRights(Side $side, string $type): bool
    {
        return match ($side) {
            Side::WHITE => match ($type) {
                'kingside' => $this->whiteKingside,
                'queenside' => $this->whiteQueenside,
                default => false,
            },
            Side::BLACK => match ($type) {
                'kingside' => $this->blackKingside,
                'queenside' => $this->blackQueenside,
                default => false,
            },
        };
    }
}