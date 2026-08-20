<?php

declare(strict_types=1);

namespace App\Domain\Chess\Exception;

final class ChessGameException extends ChessDomainException
{
    public static function becausePlayersMustNotHaveTheSameName(): self
    {
        return new self('Players must not be the same!');
    }

    public static function becausePlayersMustNotHaveTheSameSide(): self
    {
        return new self('Players must not have the same side!');
    }

    public static function becauseGameIsNotInProgress(): self
    {
        return new self('Game is not in progress');
    }

    public static function becauseInvalidMove(): self
    {
        return new self('Invalid move');
    }

}
