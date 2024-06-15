<?php

declare(strict_types=1);

namespace App\Domain\Chess;

/**
 *
 */
class GameId
{
    private function __construct(
        public readonly string $id
    )
    {
    }

    public static function fromString(string $string): self
    {
        return new self($string);
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
