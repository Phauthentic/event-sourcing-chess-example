<?php

declare(strict_types=1);

namespace App\Domain\Chess;

enum CastlingSide: string
{
    case KINGSIDE = 'kingside';
    case QUEENSIDE = 'queenside';
    case BOTH = 'both';
}
