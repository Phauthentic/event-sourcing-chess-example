<?php

declare(strict_types=1);

namespace App\Domain\Chess;

enum MoveKind
{
    case STANDARD;
    case EN_PASSANT;
    case CASTLING;
}
