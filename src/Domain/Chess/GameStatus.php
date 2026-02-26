<?php

declare(strict_types=1);

namespace App\Domain\Chess;

/**
 * Represents the current status of a chess game.
 */
enum GameStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case CHECKMATE = 'checkmate';
    case STALEMATE = 'stalemate';
    case DRAW_AGREED = 'draw_agreed';
    case RESIGNATION = 'resignation';
}