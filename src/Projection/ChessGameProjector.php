<?php

declare(strict_types=1);

namespace App\Projection;

use App\Domain\Chess\Event\CheckAnnounced;
use App\Domain\Chess\Event\DrawAccepted;
use App\Domain\Chess\Event\DrawOffered;
use App\Domain\Chess\Event\GameFinished;
use App\Domain\Chess\Event\GameStarted;
use App\Domain\Chess\Event\PieceCaptured;
use App\Domain\Chess\Event\PieceMoved;
use App\Entity\ChessGameReadModel;
use Doctrine\ORM\EntityManagerInterface;
use Phauthentic\EventSourcing\Projection\ResettableProjectorInterface;

/**
 * Projector for chess game read models.
 *
 * Maintains a read-optimized view of chess game state by handling domain events.
 */
class ChessGameProjector implements ResettableProjectorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Determines if this projector supports the given event.
     */
    public function supports(object $event): bool
    {
        return $event instanceof GameStarted
            || $event instanceof PieceMoved
            || $event instanceof PieceCaptured
            || $event instanceof CheckAnnounced
            || $event instanceof DrawOffered
            || $event instanceof DrawAccepted
            || $event instanceof GameFinished;
    }

    /**
     * Projects the event into the read model.
     */
    public function project(object $event): void
    {
        match (true) {
            $event instanceof GameStarted => $this->projectGameStarted($event),
            $event instanceof PieceMoved => $this->projectPieceMoved($event),
            $event instanceof PieceCaptured => $this->projectPieceCaptured($event),
            $event instanceof CheckAnnounced => $this->projectCheckAnnounced($event),
            $event instanceof DrawOffered => $this->projectDrawOffered($event),
            $event instanceof DrawAccepted => $this->projectDrawAccepted($event),
            $event instanceof GameFinished => $this->projectGameFinished($event),
            default => null // Should not happen due to supports() check
        };
    }

    /**
     * Resets the projection read model.
     */
    public function reset(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\ChessGameReadModel')->execute();
    }

    private function projectGameStarted(GameStarted $event): void
    {
        $readModel = new ChessGameReadModel($event->gameId);
        $readModel->setPlayerOneName($event->playerOneId);
        $readModel->setPlayerTwoName($event->playerTwoId);
        $readModel->setActivePlayer($event->playerOneSide === 'white' ? $event->playerOneId : $event->playerTwoId);
        $readModel->initializeBoard();

        $this->entityManager->persist($readModel);
        $this->entityManager->flush();
    }

    private function projectPieceMoved(PieceMoved $event): void
    {
        $readModel = $this->getReadModelByEvent($event);
        if (!$readModel) {
            return;
        }

        $board = $readModel->getBoard();

        // Find the piece at the 'from' position and move it to 'to'
        if (isset($board[$event->from])) {
            $piece = $board[$event->from];
            $board[$event->to] = $piece;
            $board[$event->from] = null;
            $readModel->setBoard($board);
        }

        // Switch active player (simplified - in a real implementation you'd track this properly)
        $currentPlayer = $readModel->getActivePlayer();
        $players = [$readModel->getPlayerOneName(), $readModel->getPlayerTwoName()];
        $otherPlayer = $players[0] === $currentPlayer ? $players[1] : $players[0];
        $readModel->setActivePlayer($otherPlayer);

        $this->entityManager->flush();
    }

    private function projectPieceCaptured(PieceCaptured $event): void
    {
        $readModel = $this->getReadModelByEvent($event);
        if (!$readModel) {
            return;
        }

        $board = $readModel->getBoard();

        // Remove the captured piece and move the capturing piece
        $board[$event->to] = $board[$event->from] ?? null;
        $board[$event->from] = null;

        $readModel->setBoard($board);

        // Switch active player
        $currentPlayer = $readModel->getActivePlayer();
        $players = [$readModel->getPlayerOneName(), $readModel->getPlayerTwoName()];
        $otherPlayer = $players[0] === $currentPlayer ? $players[1] : $players[0];
        $readModel->setActivePlayer($otherPlayer);

        $this->entityManager->flush();
    }

    private function projectCheckAnnounced(CheckAnnounced $event): void
    {
        $readModel = $this->getReadModelByEvent($event);
        if (!$readModel) {
            return;
        }

        $readModel->setIsInCheck(true);
        $this->entityManager->flush();
    }

    private function projectDrawOffered(DrawOffered $event): void
    {
        $readModel = $this->getReadModelByEvent($event);
        if (!$readModel) {
            return;
        }

        $readModel->setDrawOffered(true);
        $this->entityManager->flush();
    }

    private function projectDrawAccepted(DrawAccepted $event): void
    {
        $readModel = $this->getReadModelByEvent($event);
        if (!$readModel) {
            return;
        }

        $readModel->setDrawOffered(false);
        $readModel->setIsFinished(true);
        // No winner for a draw
        $this->entityManager->flush();
    }

    private function projectGameFinished(GameFinished $event): void
    {
        $readModel = $this->getReadModelByEvent($event);
        if (!$readModel) {
            return;
        }

        $readModel->setIsFinished(true);
        // In a real implementation, you'd determine the winner from the event
        // For now, we'll leave it as null
        $this->entityManager->flush();
    }

    /**
     * Helper method to get read model from event.
     *
     * This assumes events have a gameId property. In a real implementation,
     * you might need to extract the aggregate ID from the event differently.
     */
    private function getReadModelByEvent(object $event): ?ChessGameReadModel
    {
        // Try to get gameId from the event
        $gameId = $this->extractGameIdFromEvent($event);
        if (!$gameId) {
            return null;
        }

        return $this->entityManager->find(ChessGameReadModel::class, $gameId);
    }

    /**
     * Extract game ID from event.
     *
     * This is a simplified implementation. In a real system, you might use
     * reflection or implement an interface on events.
     */
    private function extractGameIdFromEvent(object $event): ?string
    {
        $gameId = isset($event->gameId) ? $event->gameId : null;

        return ($gameId !== null && $gameId !== '') ? $gameId : null;
    }
}