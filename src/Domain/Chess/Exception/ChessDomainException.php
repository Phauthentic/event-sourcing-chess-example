<?php

declare(strict_types=1);

namespace App\Domain\Chess\Exception;

use Exception;

class ChessDomainException extends Exception
{
    public static function playerMustNotBeTheSameSide(): self
    {
        return new self('Players must not have the same side!');
    }
}
