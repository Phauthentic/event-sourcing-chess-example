<?php

declare(strict_types=1);

namespace App\Domain\Chess\Event;

/**
 * Emitted when a pawn is promoted to another piece.
 */
class PiecePromoted
{
    public function __construct(
        public readonly string $gameId,
        public readonly string $from,
        public readonly string $to,
        public readonly string $promotedTo,
    ) {}
}