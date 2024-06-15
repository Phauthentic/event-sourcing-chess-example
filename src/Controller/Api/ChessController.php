<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Chess\Game;
use App\Domain\Chess\GameId;
use App\Domain\Chess\Player;
use App\Domain\Chess\Side;
use App\DTO\NewChessGameDto;
use Phauthentic\EventSourcing\Repository\EventSourcedRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 *
 */
#[AsController]
class ChessController
{
    public function __construct(
        private EventSourcedRepositoryInterface $eventSourcedRepository
    ) {}

    #[Route(
        path: '/api/chess/{gameId}',
        name: 'get_chess_game',
        methods: ['GET']
    )]
    public function getGame(string $gameId)
    {
        $game = $this->eventSourcedRepository->restore( $gameId, Game::class);

        return new JsonResponse([
            'game' => $game->toArray()
        ]);
    }

    #[Route(
        path: '/api/chess',
        name: 'create_chess_game',
        methods: ['POST']
    )]
    public function createGame(
        #[MapRequestPayload]
        NewChessGameDto $newChessGameDto
    ): JsonResponse
    {
        $gameId = GameId::fromString(Uuid::v4()->toRfc4122());

        $game = Game::create(
            $gameId,
            new Player($newChessGameDto->white->name, Side::WHITE),
            new Player($newChessGameDto->black->name, Side::BLACK),
        );

        $this->eventSourcedRepository->persist($game);

        return new JsonResponse([
            'gameId' => $gameId,
        ]);
    }

    #[Route(
        path: '/api/chess/{gameId}/move',
        name: 'chess_move',
        methods: ['POST']
    )]
    public function move(
        string $gameId
    ) {
        $game = $this->eventSourcedRepository->restore($gameId, Game::class);

        $game->move('e2', 'e4');
    }
}
