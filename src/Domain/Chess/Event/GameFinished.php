<?php

declare(strict_types=1);

namespace App\Domain\Chess\Event;

/**
 * Emitted when the game ends (checkmate, stalemate, draw, resignation).
 */
class GameFinished
{
    public function __construct(
        public readonly string $gameId,
        public readonly string $status,
        public readonly ?string $winner = null,
        public readonly ?string $reason = null,
    ) {}
}
