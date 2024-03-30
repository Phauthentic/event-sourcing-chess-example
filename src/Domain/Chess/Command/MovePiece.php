<?php

declare(strict_types=1);

namespace App\Domain\Chess\Command;

/**
 *
 */
class MovePiece
{
    public function __construct(
        public readonly string $from,
        public readonly string $to
    ) {
    }
}
