<?php

declare(strict_types=1);

namespace App\Projection;

use App\Domain\Chess\Event\CastlingPerformed;
use App\Domain\Chess\Event\CheckAnnounced;
use App\Domain\Chess\Event\Checkmate;
use App\Domain\Chess\Event\DrawAccepted;
use App\Domain\Chess\Event\DrawOffered;
use App\Domain\Chess\Event\GameFinished;
use App\Domain\Chess\Event\GameStarted;
use App\Domain\Chess\Event\PieceCaptured;
use App\Domain\Chess\Event\PieceMoved;
use App\Domain\Chess\Event\PiecePromoted;
use App\Domain\Chess\Event\Stalemate;
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
            || $event instanceof PiecePromoted
            || $event instanceof CastlingPerformed
            || $event instanceof CheckAnnounced
            || $event instanceof Checkmate
            || $event instanceof Stalemate
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
            $event instanceof PiecePromoted => $this->projectPiecePromoted($event),
            $event instanceof CastlingPerformed => $this->projectCastlingPerformed($event),
            $event instanceof CheckAnnounced => $this->projectCheckAnnounced($event),
            $event instanceof Checkmate => $this->projectCheckmate($event),
            $event instanceof Stalemate => $this->projectStalemate($event),
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

        if ($event->isEnPassant) {
            // For en passant, the captured pawn is not at the destination
            // It's at the same file as destination but different rank
            $fromPos = $event->from; // e.g., "d5"
            $toPos = $event->to;     // e.g., "c6"
            $capturedPos = $toPos[0] . $fromPos[1]; // e.g., "c5"

            // Move capturing piece
            $board[$toPos] = $board[$fromPos] ?? null;
            $board[$fromPos] = null;

            // Remove captured piece (en passant pawn)
            $board[$capturedPos] = null;
        } else {
            // Regular capture: remove captured piece and move capturing piece
            $board[$event->to] = $board[$event->from] ?? null;
            $board[$event->from] = null;
        }

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

    private function projectPiecePromoted(PiecePromoted $event): void
    {
        $readModel = $this->getReadModelByEvent($event);
        if (!$readModel) {
            return;
        }

        $board = $readModel->getBoard();

        // Update the piece at the promotion position
        if (isset($board[$event->to])) {
            $board[$event->to]['type'] = strtolower($event->promotedTo);
            $readModel->setBoard($board);
        }

        // Switch active player
        $currentPlayer = $readModel->getActivePlayer();
        $players = [$readModel->getPlayerOneName(), $readModel->getPlayerTwoName()];
        $otherPlayer = $players[0] === $currentPlayer ? $players[1] : $players[0];
        $readModel->setActivePlayer($otherPlayer);

        $this->entityManager->flush();
    }

    private function projectCastlingPerformed(CastlingPerformed $event): void
    {
        $readModel = $this->getReadModelByEvent($event);
        if (!$readModel) {
            return;
        }

        $board = $readModel->getBoard();

        // Move king
        if (isset($board[$event->kingFrom])) {
            $king = $board[$event->kingFrom];
            $board[$event->kingTo] = $king;
            unset($board[$event->kingFrom]);
        }

        // Move rook
        if (isset($board[$event->rookFrom])) {
            $rook = $board[$event->rookFrom];
            $board[$event->rookTo] = $rook;
            unset($board[$event->rookFrom]);
        }

        $readModel->setBoard($board);

        // Switch active player
        $currentPlayer = $readModel->getActivePlayer();
        $players = [$readModel->getPlayerOneName(), $readModel->getPlayerTwoName()];
        $otherPlayer = $players[0] === $currentPlayer ? $players[1] : $players[0];
        $readModel->setActivePlayer($otherPlayer);

        $this->entityManager->flush();
    }

    private function projectCheckmate(Checkmate $event): void
    {
        $readModel = $this->getReadModelByEvent($event);
        if (!$readModel) {
            return;
        }

        $readModel->setIsFinished(true);
        $readModel->setWinner($event->winnerSide === 'white' ? $readModel->getPlayerOneName() : $readModel->getPlayerTwoName());
        $this->entityManager->flush();
    }

    private function projectStalemate(Stalemate $event): void
    {
        $readModel = $this->getReadModelByEvent($event);
        if (!$readModel) {
            return;
        }

        $readModel->setIsFinished(true);
        // No winner for stalemate
        $this->entityManager->flush();
    }

    private function projectGameFinished(GameFinished $event): void
    {
        $readModel = $this->getReadModelByEvent($event);
        if (!$readModel) {
            return;
        }

        $readModel->setIsFinished(true);

        if ($event->winner) {
            $readModel->setWinner($event->winner === 'white' ? $readModel->getPlayerOneName() : $readModel->getPlayerTwoName());
        }

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