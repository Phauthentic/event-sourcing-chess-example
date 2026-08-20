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

    public static function becauseNoPieceOnSelectedPosition(string $position): self
    {
        return new self(sprintf('There is no piece on the selected position: %s', $position));
    }

    public static function becauseGameIsNotInProgress(): self
    {
        return new self('Game is not in progress');
    }

    public static function becauseInvalidMove(): self
    {
        return new self('Invalid move');
    }

    public static function becauseInvalidEnPassantCapture(): self
    {
        return new self('Invalid en passant capture: no pawn to capture');
    }

    public static function becauseNotYourTurn(): self
    {
        return new self('It is not your turn!');
    }
}
