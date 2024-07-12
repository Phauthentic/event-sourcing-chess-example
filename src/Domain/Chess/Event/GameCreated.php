<?php

declare(strict_types=1);

namespace App\Domain\Chess\Event;

/**
 *
 */
final readonly class GameCreated
{
    public string $gameId;
    public array $white;
    public array $black;

    private function __construct(
    ) {}

    /**
     * @param string $boardId
     * @param array $white
     * @param array $black
     * @return self
     */
    public static function create(
        string $boardId,
        array $white,
        array $black,
    ) {
        $that = new self();
        $that->gameId = $boardId;
        $that->white = $white;
        $that->black = $black;

        return $that;
    }
}
