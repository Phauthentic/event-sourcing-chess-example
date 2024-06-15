<?php

declare(strict_types=1);

namespace App\Domain\Chess\Event;

/**
 *
 */
final class GameEnded
{
    public readonly string $boardId;


    private function __construct(
    ) {}

    /**
     * @param string $boardId

     * @return self
     */
    public static function create(
        string $boardId
    ) {
        $that = new self();
        $that->boardId = $boardId;

        return $that;
    }
}
