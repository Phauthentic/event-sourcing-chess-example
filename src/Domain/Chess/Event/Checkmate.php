<?php

declare(strict_types=1);

namespace App\Domain\Chess\Event;

/**
 * Emitted when a king is checkmated and the game ends.
 */
class Checkmate
{
    public function __construct(
        public readonly string $gameId,
        public readonly string $winnerSide,
        public readonly string $loserSide,
    ) {}
}