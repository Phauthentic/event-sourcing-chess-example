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
        $this->recordAndApply(new CheckAnnounced(gameId: (string) $this->getGameId()));
    }

    public function offerDraw(): void
    {
        $this->recordAndApply(new DrawOffered(gameId: (string) $this->getGameId()));
    }

    public function acceptDraw(): void
    {
        $this->recordAndApply(new DrawAccepted(gameId: (string) $this->getGameId()));
        $this->recordAndApply(new GameFinished(
            gameId: (string) $this->getGameId(),
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

        $piece = $this->getBoard()->getPiece($from);

        match (true) {
            $this->isCastlingMove($piece, $from, $to) => $this->recordCastling($piece, $from, $to),
            $this->isEnPassantCapture($piece, $from, $to) => $this->recordEnPassantCapture($piece, $from, $to),
            default => $this->recordStandardMove($piece, $from, $to, $promotion),
        };

        $this->recordGameEndConditions($piece->side);
    }

    private function recordStandardMove(Piece $piece, Position $from, Position $to, ?PieceType $promotion): void
    {
        $board = $this->getBoard();
        if ($board->fieldHasPiece($to)) {
            $this->recordAndApply(new PieceCaptured(
                gameId: (string) $this->getGameId(),
                pieceType: $piece->type->value,
                captured: $board->getPiece($to)->type->value,
                from: $from->position,
                to: $to->position,
                isEnPassant: false
            ));
        }

        if ($promotion !== null && $piece->type === PieceType::PAWN) {
            $this->recordAndApply(new PiecePromoted(
                gameId: (string) $this->getGameId(),
                from: $from->position,
                to: $to->position,
                promotedTo: $promotion->value
            ));

            return;
        }

        $this->recordAndApply(new PieceMoved(
            gameId: (string) $this->getGameId(),
            pieceType: $piece->type->value,
            from: $from->position,
            to: $to->position
        ));
    }

    private function recordEnPassantCapture(Piece $piece, Position $from, Position $to): void
    {
        $this->recordAndApply(new PieceCaptured(
            gameId: (string) $this->getGameId(),
            pieceType: $piece->type->value,
            captured: PieceType::PAWN->value,
            from: $from->position,
            to: $to->position,
            isEnPassant: true
        ));
        $this->recordAndApply(new PieceMoved(
            gameId: (string) $this->getGameId(),
            pieceType: $piece->type->value,
            from: $from->position,
            to: $to->position
        ));
    }

    private function recordCastling(Piece $king, Position $kingFrom, Position $kingTo): void
    {
        $isKingside = $kingTo->fileIndex() > $kingFrom->fileIndex();
        $rank = $kingFrom->rank();

        $this->recordAndApply(new CastlingPerformed(
            gameId: (string) $this->getGameId(),
            side: $king->side->value,
            type: $isKingside ? 'kingside' : 'queenside',
            kingFrom: $kingFrom->position,
            kingTo: $kingTo->position,
            rookFrom: ($isKingside ? 'h' : 'a') . $rank,
            rookTo: ($isKingside ? 'f' : 'd') . $rank
        ));
    }

    private function recordGameEndConditions(Side $moverSide): void
    {
        $board = $this->getBoard();
        $opponentSide = $moverSide === Side::WHITE ? Side::BLACK : Side::WHITE;
        $isInCheck = $board->isSquareAttackedBy($board->getKingPosition($opponentSide), $moverSide);
        $opponentHasMoves = $this->hasLegalMoves($opponentSide);

        if ($isInCheck && !$opponentHasMoves) {
            $this->recordCheckmate($moverSide);

            return;
        }

        if ($isInCheck) {
            $this->recordAndApply(new CheckAnnounced(gameId: (string) $this->getGameId()));

            return;
        }

        if (!$opponentHasMoves) {
            $this->recordStalemate();
        }
    }

    private function recordCheckmate(Side $winnerSide): void
    {
        $loserSide = $winnerSide === Side::WHITE ? Side::BLACK : Side::WHITE;

        $this->recordAndApply(new Checkmate(
            gameId: (string) $this->getGameId(),
            winnerSide: $winnerSide->value,
            loserSide: $loserSide->value
        ));
        $this->recordAndApply(new GameFinished(
            gameId: (string) $this->getGameId(),
            status: GameStatus::CHECKMATE->value,
            winner: $winnerSide->value,
            reason: 'checkmate'
        ));
    }

    private function recordStalemate(): void
    {
        $this->recordAndApply(new Stalemate(gameId: (string) $this->getGameId()));
        $this->recordAndApply(new GameFinished(
            gameId: (string) $this->getGameId(),
            status: GameStatus::STALEMATE->value,
            winner: null,
            reason: 'stalemate'
        ));
    }

    private function endTurn(): void
    {
        $this->activePlayer = $this->activePlayer === $this->playerOne ? $this->playerTwo : $this->playerOne;
    }

    private function isCastlingMove(Piece $piece, Position $from, Position $to): bool
    {
        if ($piece->type !== PieceType::KING) {
            return false;
        }

        [$fileDelta, $rankDelta] = $from->distanceTo($to);

        return $rankDelta === 0 && abs($fileDelta) === 2;
    }

    private function isEnPassantCapture(Piece $piece, Position $from, Position $to): bool
    {
        if ($piece->type !== PieceType::PAWN || $this->enPassantTarget === null) {
            return false;
        }

        [$fileDelta] = $from->distanceTo($to);

        return abs($fileDelta) === 1 && $to->position === $this->enPassantTarget->position;
    }

    private function updateCastlingRights(Piece $piece, Position $from): void
    {
        if ($piece->type === PieceType::KING) {
            $this->castlingRights = $this->getCastlingRights()->revokeForSide($piece->side, 'both');

            return;
        }

        if ($piece->type !== PieceType::ROOK) {
            return;
        }

        $isKingsideRook = ($piece->side === Side::WHITE && $from->position === 'h1') ||
                          ($piece->side === Side::BLACK && $from->position === 'h8');
        $type = $isKingsideRook ? 'kingside' : 'queenside';
        $this->castlingRights = $this->getCastlingRights()->revokeForSide($piece->side, $type);
    }

    private function updateEnPassantTarget(Piece $piece, Position $from, Position $to): void
    {
        [, $rankDelta] = $from->distanceTo($to);
        if ($piece->type !== PieceType::PAWN || abs($rankDelta) !== 2) {
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
        $tempGame->board = $this->getBoard()->clone();
        $tempGame->activePlayer = $side === Side::WHITE ? $this->playerOne : $this->playerTwo;
        $tempGame->castlingRights = $this->castlingRights;
        $tempGame->enPassantTarget = $this->enPassantTarget;
        $tempGame->status = GameStatus::IN_PROGRESS;

        return $this->checkAllPiecesForTheGivenSideForLegalMoves($tempGame, $side);
    }

    private function checkAllPiecesForTheGivenSideForLegalMoves(self $tempGame, Side $side): bool
    {
        foreach ($this->getBoard()->getAllPositions() as $fromPosition) {
            if (!$tempGame->getBoard()->fieldHasPiece($fromPosition)) {
                continue;
            }

            $piece = $tempGame->getBoard()->getPiece($fromPosition);
            if ($piece->side !== $side) {
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
        foreach ($this->getBoard()->getAllPositions() as $toPosition) {
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

    public function getActivePlayer(): Player
    {
        \assert($this->activePlayer !== null);

        return $this->activePlayer;
    }

    public function getBoard(): Board
    {
        \assert($this->board !== null);

        return $this->board;
    }

    public function getGameId(): GameId
    {
        return $this->gameId ?? GameId::fromString($this->aggregateId);
    }

    public function getCastlingRights(): CastlingRights
    {
        return $this->castlingRights ?? CastlingRights::initial();
    }

    public function getEnPassantTarget(): ?Position
    {
        return $this->enPassantTarget;
    }

    public function getStatus(): GameStatus
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
        $piece = $this->getBoard()->getPiece($from);

        $this->getBoard()->movePiece($piece, $to);
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

        $this->getBoard()->removePiece($capturedSquare);
    }

    protected function whenPiecePromoted(PiecePromoted $event): void
    {
        $from = Position::fromString($event->from);
        $to = Position::fromString($event->to);
        $pawn = $this->getBoard()->getPiece($from);

        $this->getBoard()->movePiece($pawn, $to);
        $pawn->promote(PieceType::from($event->promotedTo));
        $this->enPassantTarget = null;
        $this->endTurn();
    }

    protected function whenCastlingPerformed(CastlingPerformed $event): void
    {
        $board = $this->getBoard();
        $king = $board->getPiece(Position::fromString($event->kingFrom));
        $rook = $board->getPiece(Position::fromString($event->rookFrom));

        $board->movePiece($king, Position::fromString($event->kingTo));
        $board->movePiece($rook, Position::fromString($event->rookTo));

        $this->castlingRights = $this->getCastlingRights()->revokeForSide(Side::from($event->side), 'both');
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
