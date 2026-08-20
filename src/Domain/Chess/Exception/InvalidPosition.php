<?php

declare(strict_types=1);

namespace App\Domain\Chess\Exception;

final class InvalidPosition extends ChessDomainException
{
    public static function fromString(string $position): self
    {
        return new self(sprintf('Invalid position: %s', $position));
    }
}
