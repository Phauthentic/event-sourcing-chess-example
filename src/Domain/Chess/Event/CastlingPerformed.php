<?php

declare(strict_types=1);

namespace App\Domain\Chess\Event;

/**
 * Emitted when castling is performed (king and rook move simultaneously).
 */
class CastlingPerformed
{
    public function __construct(
        public readonly string $gameId,
        public readonly string $side,
        public readonly string $type, // 'kingside' or 'queenside'
        public readonly string $kingFrom,
        public readonly string $kingTo,
        public readonly string $rookFrom,
        public readonly string $rookTo,
    ) {}
}