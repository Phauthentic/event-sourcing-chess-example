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
    ) {
    }

    public static function initial(): self
    {
        return new self(true, true, true, true);
    }

    public function revokeForSide(Side $side, CastlingSide $castlingSide): self
    {
        $revokeKingside = $castlingSide !== CastlingSide::QUEENSIDE;
        $revokeQueenside = $castlingSide !== CastlingSide::KINGSIDE;

        return match ($side) {
            Side::WHITE => new self(
                $this->whiteKingside && !$revokeKingside,
                $this->whiteQueenside && !$revokeQueenside,
                $this->blackKingside,
                $this->blackQueenside
            ),
            Side::BLACK => new self(
                $this->whiteKingside,
                $this->whiteQueenside,
                $this->blackKingside && !$revokeKingside,
                $this->blackQueenside && !$revokeQueenside
            ),
        };
    }

    public function hasRights(Side $side, CastlingSide $castlingSide): bool
    {
        return match ($side) {
            Side::WHITE => match ($castlingSide) {
                CastlingSide::KINGSIDE => $this->whiteKingside,
                CastlingSide::QUEENSIDE => $this->whiteQueenside,
                CastlingSide::BOTH => $this->whiteKingside && $this->whiteQueenside,
            },
            Side::BLACK => match ($castlingSide) {
                CastlingSide::KINGSIDE => $this->blackKingside,
                CastlingSide::QUEENSIDE => $this->blackQueenside,
                CastlingSide::BOTH => $this->blackKingside && $this->blackQueenside,
            },
        };
    }
}
