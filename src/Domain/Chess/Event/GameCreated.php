<?php

declare(strict_types=1);

namespace App\Domain\Chess\Event;

/**
 *
 */
final class GameCreated
{
    public readonly string $boardId;
    public readonly  string $playerWhiteName;
    public readonly  string $playerBlackName;

    private function __construct(
    ) {}

    /**
     * @param string $boardId
     * @param string $playerWhiteName
     * @param string $playerBlackName
     * @return self
     */
    public static function create(
        string $boardId,
        string $playerWhiteName,
        string $playerBlackName,
    ) {
        $that = new self();
        $that->boardId = $boardId;
        $that->playerWhiteName = $playerWhiteName;
        $that->playerBlackName = $playerBlackName;

        return $that;
    }
}
