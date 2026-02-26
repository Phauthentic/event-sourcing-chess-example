<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Chess\Board;
use App\Domain\Chess\Game;
use App\Domain\Chess\GameId;
use App\Domain\Chess\Player;
use App\Domain\Chess\Position;
use App\Domain\Chess\Side;
use App\Entity\ChessGameReadModel;
use Doctrine\ORM\EntityManagerInterface;
use Phauthentic\EventSourcing\Repository\EventSourcedRepositoryInterface;
use Symfony\Component\Uid\Uuid;

class ChessGameService
{
    public function __construct(
        private EventSourcedRepositoryInterface $repository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function createGame(string $playerOneName, string $playerTwoName): string
    {
        $gameId = GameId::fromString((string) Uuid::v4());
        $game = Game::create(
            $gameId,
            new Player($playerOneName, Side::WHITE),
            new Player($playerTwoName, Side::BLACK),
            new Board()
        );

        $this->repository->persist($game);

        return $gameId->id;
    }

    public function getGame(string $gameId): Game
    {
        /** @var Game $game */
        $game = $this->repository->restore($gameId, Game::class);

        if ($game->getBoard() === null) {
            throw new \RuntimeException('Game not found');
        }

        return $game;
    }

    public function move(string $gameId, string $from, string $to): void
    {
        $game = $this->getGame($gameId);
        $game->move(
            Position::fromString($from),
            Position::fromString($to)
        );
        $this->repository->persist($game);
    }

    public function getBoardState(string $gameId): array
    {
        // Try to get state from read model first (projection)
        $readModel = $this->entityManager->find(ChessGameReadModel::class, $gameId);

        if ($readModel !== null) {
            return [
                'board' => $readModel->getBoard(),
                'activePlayer' => $readModel->getActivePlayer(),
            ];
        }

        // Fallback to aggregate state (temporary migration safety)
        $game = $this->getGame($gameId);
        $board = $game->getBoard();

        $squares = [];
        $files = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
        for ($rank = 8; $rank >= 1; $rank--) {
            foreach ($files as $file) {
                $pos = $file . $rank;
                $position = Position::fromString($pos);
                $squares[$pos] = null;
                if ($board->fieldHasPiece($position)) {
                    $piece = $board->getPiece($position);
                    $squares[$pos] = [
                        'type' => $piece->type->value,
                        'side' => $piece->side->value,
                        'symbol' => $piece->toSymbol(),
                    ];
                }
            }
        }

        return [
            'board' => $squares,
            'activePlayer' => $game->getActivePlayer()->name,
        ];
    }

    public function announceCheck(string $gameId): void
    {
        $game = $this->getGame($gameId);
        $game->announceCheck();
        $this->repository->persist($game);
    }

    public function offerDraw(string $gameId): void
    {
        $game = $this->getGame($gameId);
        $game->offerDraw();
        $this->repository->persist($game);
    }

    public function acceptDraw(string $gameId): void
    {
        $game = $this->getGame($gameId);
        $game->acceptDraw();
        $this->repository->persist($game);
    }
}
