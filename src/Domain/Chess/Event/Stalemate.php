<?php

declare(strict_types=1);

namespace App\Domain\Chess\Event;

/**
 * Emitted when a player has no legal moves and their king is not in check (stalemate).
 */
class Stalemate
{
    public function __construct(
        public readonly string $gameId,
    ) {}
}