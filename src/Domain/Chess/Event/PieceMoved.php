<?php

declare(strict_types=1);

namespace App\Domain\Chess\Event;

/**
 *
 */
class PieceMoved
{
    public function __construct(
        public readonly string $playerName,
        public readonly string $pieceType,
        public readonly string $side,
        public readonly string $from,
        public readonly string $to,
        public readonly string $capturedPiece = ''
    )
    {
    }
}
