<?php

declare(strict_types=1);

namespace App\Domain\Chess\Exception;

use App\Domain\Chess\Position;

final class NoPieceOnSquare extends ChessDomainException
{
    public static function at(Position $position): self
    {
        return new self(sprintf('There is no piece on square %s', $position->position));
    }
}
