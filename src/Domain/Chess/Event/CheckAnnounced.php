<?php

declare(strict_types=1);

namespace App\Domain\Chess\Event;

/**
 *
 */
class CheckAnnounced
{
    public function __construct(
        public readonly string $gameId = ''
    ) {
    }
}
