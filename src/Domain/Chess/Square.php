<?php

declare(strict_types=1);

namespace App\Domain\Chess;

/**
 *
 */
enum Square: string
{
    case WHITE = '⬜';
    case BLACK = '⬛';
}
