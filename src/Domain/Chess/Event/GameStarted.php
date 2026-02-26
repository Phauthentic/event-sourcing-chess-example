<?php

declare(strict_types=1);

namespace App\Domain\Chess\Event;

/**
 *
 */
class GameStarted
{
    public function __construct(
        public readonly string $gameId,
        public readonly string $playerOneId,
        public readonly string $playerTwoId,
        public readonly string $playerOneSide = 'white',
        public readonly string $playerTwoSide = 'black'
    ) {
    }
}
