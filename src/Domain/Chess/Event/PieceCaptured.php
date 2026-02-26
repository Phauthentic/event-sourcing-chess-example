<?php

declare(strict_types=1);

namespace App\Domain\Chess\Event;

/**
 *
 */
class PieceCaptured
{
    public function __construct(
        public readonly string $gameId = '',
        public readonly string $pieceType = '',
        public readonly string $captured = '',
        public readonly string $from = '',
        public readonly string $to = '',
    ) {
    }
}
