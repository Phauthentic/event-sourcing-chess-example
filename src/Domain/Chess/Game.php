<?php

declare(strict_types=1);

namespace App\Domain\Chess;

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
use App\Domain\Chess\Exception\ChessGameException;
use App\Domain\Chess\Exception\NoPieceOnSquare;
use App\Domain\Chess\Service\MoveValidator;
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

    /**
     * @var array<int, object>
     */
    #[DomainEvents]
    protected array $domainEvents = [];

    #[AggregateVersion]
    protected int $aggregateVersion = 0;

    private ?GameId $gameId = null;
    private ?Player $playerOne = null;
    private ?Player $playerTwo = null;
    private ?Board $board = null;
    private ?Player $activePlayer = null;
    private ?CastlingRights $castlingRights = null;
    private ?Position $enPassantTarget = null;
    private GameStatus $status = GameStatus::IN_PROGRESS;
    private ?MoveValidator $moveValidator = null;

    private function __construct()
    {
    }

    public static function create(GameId $gameId, Player $playerOne, Player $playerTwo, Board $board): self
    {
        self::assertPlayersAreNotTheSame($playerOne, $playerTwo);
        self::assertPlayersAreNotOnTheSameSide($playerOne, $playerTwo);

        $that = new self();
        $that->aggregateId = (string) $gameId;
        $that->recordAndApply(new GameStarted(
            gameId: $gameId->id,
            playerOneId: $playerOne->name,
            playerTwoId: $playerTwo->name,
            playerOneSide: $playerOne->side->value,
            playerTwoSide: $playerTwo->side->value,
            fen: $board->toFen()
        ));

        return $that;
    }

    private static function assertPlayersAreNotTheSame(Player $playerOne, Player $playerTwo): void
    {
        if ($playerOne->name === $playerTwo->name) {
            throw ChessGameException::becausePlayersMustNotHaveTheSameName();
        }
    }

    private static function assertPlayersAreNotOnTheSameSide(Player $playerOne, Player $playerTwo): void
    {
        if ($playerOne->side === $playerTwo->side) {
            throw ChessGameException::becausePlayersMustNotHaveTheSameSide();
        }
    }

    /**
     * Records the event for persistence and applies it to the aggregate state.
     *
     * recordThat() already increments the aggregate version, so the when* handler
     * is invoked directly instead of via applyEvent(), which would bump the
     * version a second time. This keeps the write path and the replay path
     * running through the exact same state transitions.
     */
    private function recordAndApply(object $event): void
    {
        $this->recordThat($event);
        $this->{$this->getEventNameFromEvent($event)}($event);
    }

    public function announceCheck(): void
    {
        $this->recordAndApply(new CheckAnnounced(gameId: (string) $this->gameId()));
    }

    public function offerDraw(): void
    {
        $this->recordAndApply(new DrawOffered(gameId: (string) $this->gameId()));
    }

    public function acceptDraw(): void
    {
        $this->recordAndApply(new DrawAccepted(gameId: (string) $this->gameId()));
        $this->recordAndApply(new GameFinished(
            gameId: (string) $this->gameId(),
            status: GameStatus::DRAW_AGREED->value,
            winner: null,
            reason: 'draw agreed'
        ));
    }

    public function move(Position $from, Position $to, ?PieceType $promotion = null): void
    {
        if ($this->status !== GameStatus::IN_PROGRESS) {
            throw ChessGameException::becauseGameIsNotInProgress();
        }

        if (!$this->moveValidator()->isMoveLegal($this, $from, $to, $promotion)) {
            throw ChessGameException::becauseInvalidMove();
        }

        $piece = $this->pieceAt($from);

        match ($this->moveValidator()->moveKind($this->board(), $from, $to, $this->enPassantTarget)) {
            MoveKind::CASTLING => $this->recordCastling($piece, $from, $to),
            MoveKind::EN_PASSANT => $this->recordEnPassantCapture($piece, $from, $to),
            MoveKind::STANDARD => $this->recordStandardMove($piece, $from, $to, $promotion),
        };

        $this->recordGameEndConditions($piece->side);
    }

    private function pieceAt(Position $position): Piece
    {
        return $this->board()->pieceAt($position) ?? throw NoPieceOnSquare::at($position);
    }

    private function recordStandardMove(Piece $piece, Position $from, Position $to, ?PieceType $promotion): void
    {
        $captured = $this->board()->pieceAt($to);
        if ($captured !== null) {
            $this->recordAndApply(new PieceCaptured(
                gameId: (string) $this->gameId(),
                pieceType: $piece->type->value,
                captured: $captured->type->value,
                from: $from->position,
                to: $to->position,
                isEnPassant: false
            ));
        }

        if ($promotion !== null && $piece->type === PieceType::PAWN) {
            $this->recordAndApply(new PiecePromoted(
                gameId: (string) $this->gameId(),
                from: $from->position,
                to: $to->position,
                promotedTo: $promotion->value
            ));

            return;
        }

        $this->recordAndApply(new PieceMoved(
            gameId: (string) $this->gameId(),
            pieceType: $piece->type->value,
            from: $from->position,
            to: $to->position
        ));
    }

    private function recordEnPassantCapture(Piece $piece, Position $from, Position $to): void
    {
        $this->recordAndApply(new PieceCaptured(
            gameId: (string) $this->gameId(),
            pieceType: $piece->type->value,
            captured: PieceType::PAWN->value,
            from: $from->position,
            to: $to->position,
            isEnPassant: true
        ));
        $this->recordAndApply(new PieceMoved(
            gameId: (string) $this->gameId(),
            pieceType: $piece->type->value,
            from: $from->position,
            to: $to->position
        ));
    }

    private function recordCastling(Piece $king, Position $kingFrom, Position $kingTo): void
    {
        $isKingside = $kingTo->fileIndex() > $kingFrom->fileIndex();
        $castlingSide = $isKingside ? CastlingSide::KINGSIDE : CastlingSide::QUEENSIDE;
        $rank = $kingFrom->rank();

        $this->recordAndApply(new CastlingPerformed(
            gameId: (string) $this->gameId(),
            side: $king->side->value,
            type: $castlingSide->value,
            kingFrom: $kingFrom->position,
            kingTo: $kingTo->position,
            rookFrom: ($isKingside ? 'h' : 'a') . $rank,
            rookTo: ($isKingside ? 'f' : 'd') . $rank
        ));
    }

    private function recordGameEndConditions(Side $moverSide): void
    {
        $board = $this->board();
        $opponentSide = $moverSide->opponent();
        $isInCheck = $this->moveValidator()->isSquareAttackedBy($board, $board->kingPosition($opponentSide), $moverSide);
        $opponentHasMoves = $this->hasLegalMoves($opponentSide);

        if ($isInCheck && !$opponentHasMoves) {
            $this->recordCheckmate($moverSide);

            return;
        }

        if ($isInCheck) {
            $this->recordAndApply(new CheckAnnounced(gameId: (string) $this->gameId()));

            return;
        }

        if (!$opponentHasMoves) {
            $this->recordStalemate();
        }
    }

    private function recordCheckmate(Side $winnerSide): void
    {
        $this->recordAndApply(new Checkmate(
            gameId: (string) $this->gameId(),
            winnerSide: $winnerSide->value,
            loserSide: $winnerSide->opponent()->value
        ));
        $this->recordAndApply(new GameFinished(
            gameId: (string) $this->gameId(),
            status: GameStatus::CHECKMATE->value,
            winner: $winnerSide->value,
            reason: 'checkmate'
        ));
    }

    private function recordStalemate(): void
    {
        $this->recordAndApply(new Stalemate(gameId: (string) $this->gameId()));
        $this->recordAndApply(new GameFinished(
            gameId: (string) $this->gameId(),
            status: GameStatus::STALEMATE->value,
            winner: null,
            reason: 'stalemate'
        ));
    }

    private function endTurn(): void
    {
        $this->activePlayer = $this->activePlayer === $this->playerOne ? $this->playerTwo : $this->playerOne;
    }

    private function updateCastlingRights(Piece $piece, Position $from): void
    {
        if ($piece->type === PieceType::KING) {
            $this->castlingRights = $this->castlingRights()->revokeForSide($piece->side, CastlingSide::BOTH);

            return;
        }

        if ($piece->type !== PieceType::ROOK) {
            return;
        }

        $isKingsideRook = ($piece->side === Side::WHITE && $from->position === 'h1') ||
                          ($piece->side === Side::BLACK && $from->position === 'h8');
        $castlingSide = $isKingsideRook ? CastlingSide::KINGSIDE : CastlingSide::QUEENSIDE;
        $this->castlingRights = $this->castlingRights()->revokeForSide($piece->side, $castlingSide);
    }

    private function updateEnPassantTarget(Piece $piece, Position $from, Position $to): void
    {
        if ($piece->type !== PieceType::PAWN || abs($from->rankDistanceTo($to)) !== 2) {
            $this->enPassantTarget = null;

            return;
        }

        $targetRank = $piece->side === Side::WHITE ? $from->rank() + 1 : $from->rank() - 1;
        $this->enPassantTarget = new Position($from->file() . $targetRank);
    }

    private function hasLegalMoves(Side $side): bool
    {
        $tempGame = new self();
        $tempGame->gameId = $this->gameId;
        $tempGame->playerOne = $this->playerOne;
        $tempGame->playerTwo = $this->playerTwo;
        $tempGame->board = clone $this->board();
        $tempGame->activePlayer = $side === Side::WHITE ? $this->playerOne : $this->playerTwo;
        $tempGame->castlingRights = $this->castlingRights;
        $tempGame->enPassantTarget = $this->enPassantTarget;
        $tempGame->status = GameStatus::IN_PROGRESS;

        foreach (Position::all() as $fromPosition) {
            $piece = $tempGame->board()->pieceAt($fromPosition);
            if ($piece === null || $piece->side !== $side) {
                continue;
            }

            if ($this->hasLegalMoveFromSquare($tempGame, $fromPosition)) {
                return true;
            }
        }

        return false;
    }

    private function hasLegalMoveFromSquare(self $game, Position $fromPosition): bool
    {
        foreach (Position::all() as $toPosition) {
            if ($fromPosition->position === $toPosition->position) {
                continue;
            }

            if ($this->moveValidator()->isMoveLegal($game, $fromPosition, $toPosition)) {
                return true;
            }
        }

        return false;
    }

    private function moveValidator(): MoveValidator
    {
        return $this->moveValidator ??= new MoveValidator();
    }

    public function activePlayer(): Player
    {
        \assert($this->activePlayer !== null);

        return $this->activePlayer;
    }

    public function board(): Board
    {
        \assert($this->board !== null);

        return $this->board;
    }

    public function gameId(): GameId
    {
        return $this->gameId ?? GameId::fromString($this->aggregateId);
    }

    public function castlingRights(): CastlingRights
    {
        return $this->castlingRights ?? CastlingRights::initial();
    }

    public function enPassantTarget(): ?Position
    {
        return $this->enPassantTarget;
    }

    public function status(): GameStatus
    {
        return $this->status;
    }

    public function getAggregateVersion(): int
    {
        return $this->aggregateVersion;
    }

    /**
     * @param \Iterator<int, EventInterface>|array<int, EventInterface>|\Generator<int, EventInterface> $events
     */
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
        $this->board = Board::fromFen($event->fen);
        $this->activePlayer = $this->playerOne->side === Side::WHITE ? $this->playerOne : $this->playerTwo;
        $this->castlingRights = CastlingRights::initial();
        $this->enPassantTarget = null;
        $this->status = GameStatus::IN_PROGRESS;
    }

    protected function whenPieceMoved(PieceMoved $event): void
    {
        $from = Position::fromString($event->from);
        $to = Position::fromString($event->to);
        $piece = $this->pieceAt($from);

        $this->board()->move($from, $to);
        $this->updateCastlingRights($piece, $from);
        $this->updateEnPassantTarget($piece, $from, $to);
        $this->endTurn();
    }

    /**
     * Only removes the captured piece. The capturing piece is moved by the
     * PieceMoved or PiecePromoted event recorded for the same ply.
     */
    protected function whenPieceCaptured(PieceCaptured $event): void
    {
        $capturedSquare = $event->isEnPassant
            ? new Position($event->to[0] . $event->from[1])
            : Position::fromString($event->to);

        $this->board()->remove($capturedSquare);
    }

    protected function whenPiecePromoted(PiecePromoted $event): void
    {
        $from = Position::fromString($event->from);
        $to = Position::fromString($event->to);
        $pawn = $this->pieceAt($from);

        $this->board()->remove($from);
        $this->board()->place($to, new Piece(PieceType::from($event->promotedTo), $pawn->side));
        $this->enPassantTarget = null;
        $this->endTurn();
    }

    protected function whenCastlingPerformed(CastlingPerformed $event): void
    {
        $board = $this->board();
        $board->move(Position::fromString($event->kingFrom), Position::fromString($event->kingTo));
        $board->move(Position::fromString($event->rookFrom), Position::fromString($event->rookTo));

        $this->castlingRights = $this->castlingRights()->revokeForSide(Side::from($event->side), CastlingSide::BOTH);
        $this->enPassantTarget = null;
        $this->endTurn();
    }

    protected function whenCheckAnnounced(CheckAnnounced $event): void
    {
    }

    protected function whenCheckmate(Checkmate $event): void
    {
        $this->status = GameStatus::CHECKMATE;
    }

    protected function whenStalemate(Stalemate $event): void
    {
        $this->status = GameStatus::STALEMATE;
    }

    protected function whenDrawOffered(DrawOffered $event): void
    {
    }

    protected function whenDrawAccepted(DrawAccepted $event): void
    {
    }

    protected function whenGameFinished(GameFinished $event): void
    {
        $this->status = GameStatus::from($event->status);
    }
}
