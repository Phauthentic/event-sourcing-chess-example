<?php

declare(strict_types=1);

namespace App\Domain\Chess;

use App\Domain\Chess\Event\CheckAnnounced;
use App\Domain\Chess\Event\GameStarted;
use App\Domain\Chess\Event\PieceCaptured;
use App\Domain\Chess\Event\PieceMoved;
use App\Domain\Chess\Exception\ChessDomainException;
use Phauthentic\EventSourcing\Aggregate\Attribute\AggregateIdentifier;
use Phauthentic\EventSourcing\Aggregate\Attribute\AggregateVersion;
use Phauthentic\EventSourcing\Aggregate\Attribute\DomainEvents;

/**
 * @link https://en.wikipedia.org/wiki/Algebraic_notation_(chess)
 */
class Game
{
    #[AggregateIdentifier]
    private GameId $gameId;

    #[DomainEvents]
    private array $domainEvents = [];

    #[AggregateVersion]
    private int $aggregateVersion = 0;

    private Player $activePlayer;

    public function __construct(
        GameId $gameId,
        private Player $playerOne,
        private Player $playerTwo,
        private Board $board
    ) {
        $this->assertPlayersDonNotHaveTheSameSide($playerOne, $playerTwo);
        $this->assertPlayersAreNotTheSame($playerOne, $playerTwo);

        $this->gameId = $gameId;
        $this->playerOne = $playerOne;
        $this->playerTwo = $playerTwo;

        $this->assertPlayersAreNotOnTheSameSide();
        $this->determineStartingPlayer();

        $this->recordThat(new GameStarted(
            gameId: $gameId->id,
            playerOneId: $playerOne->name,
            playerTwoId: $playerTwo->name
        ));
    }

    private function assertPlayersAreNotTheSame(Player $playerOne, Player $playerTwo): void
    {
        if ($playerOne->name === $playerTwo->name) {
            throw new ChessDomainException('Players must not be the same!');
        }
    }

    private function assertPlayersAreNotOnTheSameSide(): void
    {
        if ($this->playerOne->side === $this->playerTwo->side) {
            throw ChessDomainException::playerMustNotBeTheSameSide();
        }
    }

    private function determineStartingPlayer(): void
    {
        $this->activePlayer = $this->playerOne;
        if ($this->playerTwo->side === Side::WHITE) {
            $this->activePlayer = $this->playerOne;
        }
    }

    public function announceCheck() {
        $this->recordThat(new CheckAnnounced(
        ));

        $this->endTurn();
    }

    public function offerDraw()
    {
        $this->endTurn();
    }

    public function acceptDraw()
    {
        $this->endTurn();
    }

    public function declineDraw()
    {
        $this->endTurn();
    }

    private function assertBoardHasPieceOnPosition(Position $position): void
    {
        if (!$this->board->fieldHasPiece($position)) {
            throw new ChessDomainException(sprintf(
                'There is no piece on the selected position: %s',
                $position->toString()
            ));
        }
    }

    public function move(Position $from, Position $to): void
    {
        $this->assertBoardHasPieceOnPosition($from);
        $this->assertActivePlayerOwnsThePiece($this->board->getPiece($from));

        $this->board->fieldHasPiece($to)
            ? $this->capturePiece($from, $to)
            : $this->movePiece($from, $to);

        $this->endTurn();
    }

    private function movePiece(Position $from, Position $to): void
    {
        $piece = $this->board->getPiece($from);

        $this->assertActivePlayerOwnsThePiece($piece);

        $this->board->movePiece($piece, $to);

        $this->recordThat(new PieceMoved(
            pieceType: $piece->type->value,
            from: $from->toString(),
            to: $to->toString()
        ));
    }

    private function capturePiece(Position $from, Position $to): void
    {
        $piece = $this->board->getPiece($from);
        $capturedPiece = $this->board->getPiece($to);

        $this->assertActivePlayerOwnsThePiece($piece);

        $this->board->removePiece($to);
        $this->board->movePiece($from, $to);

        $this->recordThat(new PieceCaptured(
            pieceType: $piece->type->value,
            captured: $capturedPiece->type->value,
            from: $from->toString(),
            to: $to->toString()
        ));
    }

    private function randomPlayerSelection()
    {
        $this->activePlayer = rand(0, 1) === 0 ? $this->playerOne : $this->playerTwo;
    }

    private function assertPlayersDonNotHaveTheSameSide(Player $playerWhite, Player $playerBlack): void
    {
        assert($playerWhite->side === $playerBlack->side, 'Players must not have the same side!');
    }

    private function endTurn()
    {
        $this->activePlayer = $this->activePlayer === $this->playerOne ? $this->playerTwo : $this->playerOne;
    }

    private function assertActivePlayerOwnsThePiece(Piece $piece): void
    {
        if ($this->activePlayer->side !== $piece->side) {
            throw new ChessDomainException('It is not your turn!');
        }
    }

    private function recordThat(object $event): void
    {
        $this->domainEvents[] = $event;
        $this->aggregateVersion++;
    }

    public function getActivePlayer(): Player
    {
        return $this->activePlayer;
    }
}
