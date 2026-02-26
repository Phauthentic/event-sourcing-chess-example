<?php

declare(strict_types=1);

namespace App\Domain\Chess;

use App\Domain\Chess\Event\CheckAnnounced;
use App\Domain\Chess\Event\GameStarted;
use App\Domain\Chess\Event\PieceCaptured;
use App\Domain\Chess\Event\PieceMoved;
use App\Domain\Chess\Exception\ChessDomainException;
use Phauthentic\EventSourcing\Aggregate\AbstractEventSourcedAggregate;
use Phauthentic\EventSourcing\Aggregate\Attribute\AggregateIdentifier;
use Phauthentic\EventSourcing\Aggregate\Attribute\AggregateVersion;
use Phauthentic\EventSourcing\Aggregate\Attribute\DomainEvents;
use Phauthentic\EventStore\EventInterface;

/**
 * @link https://en.wikipedia.org/wiki/Algebraic_notation_(chess)
 */
class Game extends AbstractEventSourcedAggregate
{
    #[AggregateIdentifier]
    protected string $aggregateId = '';

    #[DomainEvents]
    protected array $domainEvents = [];

    #[AggregateVersion]
    protected int $aggregateVersion = 0;

    private ?GameId $gameId = null;
    private ?Player $playerOne = null;
    private ?Player $playerTwo = null;
    private ?Board $board = null;
    private ?Player $activePlayer = null;

    private function __construct()
    {
    }

    public static function create(GameId $gameId, Player $playerOne, Player $playerTwo, Board $board): self
    {
        $that = new self();
        $that->assertPlayersDonNotHaveTheSameSide($playerOne, $playerTwo);
        $that->assertPlayersAreNotTheSame($playerOne, $playerTwo);
        $that->assertPlayersAreNotOnTheSameSide($playerOne, $playerTwo);

        $that->aggregateId = (string) $gameId;
        $that->gameId = $gameId;
        $that->playerOne = $playerOne;
        $that->playerTwo = $playerTwo;
        $that->board = $board;
        $that->activePlayer = $playerOne->side === Side::WHITE ? $playerOne : $playerTwo;

        $that->recordThat(new GameStarted(
            gameId: $gameId->id,
            playerOneId: $playerOne->name,
            playerTwoId: $playerTwo->name,
            playerOneSide: $playerOne->side->value,
            playerTwoSide: $playerTwo->side->value
        ));

        return $that;
    }

    private function assertPlayersAreNotTheSame(Player $playerOne, Player $playerTwo): void
    {
        if ($playerOne->name === $playerTwo->name) {
            throw new ChessDomainException('Players must not be the same!');
        }
    }

    private function assertPlayersAreNotOnTheSameSide(Player $playerOne, Player $playerTwo): void
    {
        if ($playerOne->side === $playerTwo->side) {
            throw ChessDomainException::playerMustNotBeTheSameSide();
        }
    }

    private function assertPlayersDonNotHaveTheSameSide(Player $playerOne, Player $playerTwo): void
    {
        assert($playerOne->side !== $playerTwo->side, 'Players must not have the same side!');
    }

    public function announceCheck(): void
    {
        $this->recordThat(new CheckAnnounced(gameId: (string) $this->getGameId()));
        $this->endTurn();
    }

    public function offerDraw(): void
    {
        $this->endTurn();
    }

    public function acceptDraw(): void
    {
        $this->endTurn();
    }

    public function declineDraw(): void
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
            gameId: (string) $this->getGameId(),
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
        $this->board->movePiece($piece, $to);

        $this->recordThat(new PieceCaptured(
            gameId: (string) $this->getGameId(),
            pieceType: $piece->type->value,
            captured: $capturedPiece->type->value,
            from: $from->toString(),
            to: $to->toString()
        ));
    }

    private function endTurn(): void
    {
        $this->activePlayer = $this->activePlayer === $this->playerOne ? $this->playerTwo : $this->playerOne;
    }

    private function assertActivePlayerOwnsThePiece(Piece $piece): void
    {
        if ($this->activePlayer->side !== $piece->side) {
            throw new ChessDomainException('It is not your turn!');
        }
    }

    public function getActivePlayer(): Player
    {
        return $this->activePlayer;
    }

    public function getBoard(): Board
    {
        return $this->board;
    }

    public function getGameId(): GameId
    {
        return $this->gameId ?? GameId::fromString($this->aggregateId);
    }

    public function getAggregateVersion(): int
    {
        return $this->aggregateVersion;
    }

    public function applyEventsFromHistory(\Iterator|array|\Generator $events): void
    {
        $eventsArray = $events instanceof \Traversable ? iterator_to_array($events, false) : $events;
        $first = $eventsArray[0] ?? null;
        if ($first instanceof EventInterface) {
            $this->aggregateId = $first->getAggregateId();
        }
        parent::applyEventsFromHistory(new \ArrayIterator($eventsArray));
    }

    protected function whenGameStarted(GameStarted $event): void
    {
        $this->gameId = GameId::fromString($event->gameId);
        $this->playerOne = new Player($event->playerOneId, Side::from(strtolower($event->playerOneSide)));
        $this->playerTwo = new Player($event->playerTwoId, Side::from(strtolower($event->playerTwoSide)));
        $this->board = new Board();
        $this->activePlayer = $this->playerOne->side === Side::WHITE ? $this->playerOne : $this->playerTwo;
    }

    protected function whenPieceMoved(PieceMoved $event): void
    {
        $from = Position::fromString($event->from);
        $to = Position::fromString($event->to);
        $piece = $this->board->getPiece($from);
        $this->board->movePiece($piece, $to);
        $this->endTurn();
    }

    protected function whenPieceCaptured(PieceCaptured $event): void
    {
        $from = Position::fromString($event->from);
        $to = Position::fromString($event->to);
        $piece = $this->board->getPiece($from);
        $this->board->removePiece($to);
        $this->board->movePiece($piece, $to);
        $this->endTurn();
    }

    protected function whenCheckAnnounced(CheckAnnounced $event): void
    {
        $this->endTurn();
    }
}
