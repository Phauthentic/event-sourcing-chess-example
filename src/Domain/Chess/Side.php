<?php

declare(strict_types=1);

namespace App\Domain\Chess;

enum Side: string
{
    case WHITE = 'white';
    case BLACK = 'black';

    public function opponent(): self
    {
        return $this === self::WHITE ? self::BLACK : self::WHITE;
    }
}
