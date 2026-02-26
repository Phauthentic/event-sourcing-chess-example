<?php

declare(strict_types=1);

namespace App\Domain\Chess;

use App\Domain\Chess\Board;
use App\Domain\Chess\CastlingRights;
use App\Domain\Chess\Event\CastlingPerformed;
use App\Domain\Chess\Event\CheckAnnounced;
use App\Domain\Chess\Event\Checkmate;
use App\Domain\Chess\Event\GameFinished;
use App\Domain\Chess\Event\GameStarted;
use App\Domain\Chess\Event\PieceCaptured;
use App\Domain\Chess\Event\PieceMoved;
use App\Domain\Chess\Event\PiecePromoted;
use App\Domain\Chess\Event\Stalemate;
use App\Domain\Chess\Exception\ChessDomainException;
use App\Domain\Chess\GameId;
use App\Domain\Chess\GameStatus;
use App\Domain\Chess\PieceType;
use App\Domain\Chess\Player;
use App\Domain\Chess\Position;
use App\Domain\Chess\Service\MoveValidator;
use App\Domain\Chess\Side;
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
    private ?CastlingRights $castlingRights = null;
    private ?Position $enPassantTarget = null;
    private GameStatus $status;

    private function __construct()
    {
    }

    public static function create(GameId $gameId, Player $playerOne, Player $playerTwo, Board $board, bool $skipEvents = false): self
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
        $that->castlingRights = CastlingRights::initial();
        $that->enPassantTarget = null;
        $that->status = GameStatus::IN_PROGRESS;

        if (!$skipEvents) {
            $that->recordThat(new GameStarted(
                gameId: $gameId->id,
                playerOneId: $playerOne->name,
                playerTwoId: $playerTwo->name,
                playerOneSide: $playerOne->side->value,
                playerTwoSide: $playerTwo->side->value
            ));
        }

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

    public function move(Position $from, Position $to, ?PieceType $promotion = null): void
    {
        // Check if game is still in progress
        if ($this->status !== GameStatus::IN_PROGRESS) {
            throw new ChessDomainException('Game is not in progress');
        }

        // Validate the move using domain service
        $moveValidator = new MoveValidator();
        if (!$moveValidator->isMoveLegal($this, $from, $to, $promotion)) {
            throw new ChessDomainException('Invalid move');
        }

        // Execute the move
        $piece = $this->board->getPiece($from);
        $isCapture = $this->board->fieldHasPiece($to);

        // Check if this is a castling move
        if ($this->isCastlingMove($piece, $from, $to)) {
            $this->performCastling($from, $to);
        } elseif ($this->isEnPassantCapture($piece, $from, $to)) {
            $this->performEnPassantCapture($from, $to);
        } elseif ($isCapture) {
            $this->capturePiece($from, $to);
        } else {
            $this->movePiece($from, $to, $promotion);
        }

        // Update castling rights if king or rook moved
        $this->updateCastlingRights($piece, $from);

        // Set en passant target if pawn moved 2 squares
        $this->updateEnPassantTarget($piece, $from, $to);

        // Check for check/checkmate/stalemate
        $this->checkGameEndConditions();

        $this->endTurn();
    }

    private function movePiece(Position $from, Position $to, ?PieceType $promotion = null): void
    {
        $piece = $this->board->getPiece($from);
        $this->assertActivePlayerOwnsThePiece($piece);
        $this->board->movePiece($piece, $to);

        // Handle pawn promotion
        if ($promotion !== null && $piece->type === PieceType::PAWN) {
            $piece->promote($promotion);
            $this->recordThat(new PiecePromoted(
                gameId: (string) $this->getGameId(),
                from: $from->toString(),
                to: $to->toString(),
                promotedTo: $promotion->value
            ));
        } else {
            $this->recordThat(new PieceMoved(
                gameId: (string) $this->getGameId(),
                pieceType: $piece->type->value,
                from: $from->toString(),
                to: $to->toString()
            ));
        }
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
            to: $to->toString(),
            isEnPassant: false
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

    private function performCastling(Position $kingFrom, Position $kingTo): void
    {
        $king = $this->board->getPiece($kingFrom);
        $isKingside = $kingTo->fileIndex() > $kingFrom->fileIndex();
        $side = $king->side;

        // Determine rook positions
        $rookFromFile = $isKingside ? 'h' : 'a';
        $rookToFile = $isKingside ? 'f' : 'd';
        $rank = $kingFrom->rank();

        $rookFrom = new Position($rookFromFile . $rank);
        $rookTo = new Position($rookToFile . $rank);
        $rook = $this->board->getPiece($rookFrom);

        // Move both king and rook
        $this->board->movePiece($king, $kingTo);
        $this->board->movePiece($rook, $rookTo);

        // Emit castling event
        $type = $isKingside ? 'kingside' : 'queenside';
        $this->recordThat(new CastlingPerformed(
            gameId: (string) $this->getGameId(),
            side: $side->value,
            type: $type,
            kingFrom: $kingFrom->toString(),
            kingTo: $kingTo->toString(),
            rookFrom: $rookFrom->toString(),
            rookTo: $rookTo->toString()
        ));

        // Update castling rights (all rights are now lost)
        $this->castlingRights = $this->castlingRights->revokeForSide($side, 'both');
    }

    private function updateCastlingRights(Piece $piece, Position $from): void
    {
        if ($piece->type === PieceType::KING) {
            // King moved - revoke all castling rights for this side
            $this->castlingRights = $this->castlingRights->revokeForSide($piece->side, 'both');
        } elseif ($piece->type === PieceType::ROOK) {
            // Rook moved - revoke castling rights for that side
            $isKingsideRook = ($piece->side === Side::WHITE && $from->toString() === 'h1') ||
                              ($piece->side === Side::BLACK && $from->toString() === 'h8');
            $type = $isKingsideRook ? 'kingside' : 'queenside';
            $this->castlingRights = $this->castlingRights->revokeForSide($piece->side, $type);
        }
    }

    private function isEnPassantCapture(Piece $piece, Position $from, Position $to): bool
    {
        if ($piece->type !== PieceType::PAWN) {
            return false;
        }

        // Must be moving diagonally to an empty square
        [$fileDelta, $rankDelta] = $from->distanceTo($to);
        if (abs($fileDelta) !== 1) {
            return false;
        }

        $expectedRankDelta = $piece->side === Side::WHITE ? 1 : -1;
        if ($rankDelta !== $expectedRankDelta) {
            return false;
        }

        // Destination must be empty
        if ($this->board->fieldHasPiece($to)) {
            return false;
        }

        // Must match the en passant target
        $enPassantTarget = $this->enPassantTarget;
        if ($enPassantTarget === null) {
            return false;
        }

        return $to->toString() === $enPassantTarget->toString();
    }

    private function performEnPassantCapture(Position $from, Position $to): void
    {
        $piece = $this->board->getPiece($from);

        // The captured pawn is on the same rank as the capturing pawn, but on the file of the en passant target
        // For example: if white pawn on d5 captures en passant to c6, the black pawn being captured is on c5
        $capturedPawnRank = $piece->side === Side::WHITE ? $to->rank() - 1 : $to->rank() + 1;
        $capturedPawnPosition = new Position($to->file() . $capturedPawnRank);

        if (!$this->board->fieldHasPiece($capturedPawnPosition)) {
            throw new ChessDomainException('Invalid en passant capture: no pawn to capture');
        }

        $capturedPiece = $this->board->getPiece($capturedPawnPosition);

        // Move the capturing pawn
        $this->board->movePiece($piece, $to);

        // Remove the captured pawn
        $this->board->removePiece($capturedPawnPosition);

        $this->recordThat(new PieceCaptured(
            gameId: (string) $this->getGameId(),
            pieceType: $piece->type->value,
            captured: $capturedPiece->type->value,
            from: $from->toString(),
            to: $to->toString(),
            isEnPassant: true
        ));
    }

    private function updateEnPassantTarget(Piece $piece, Position $from, Position $to): void
    {
        if ($piece->type === PieceType::PAWN) {
            [$fileDelta, $rankDelta] = $from->distanceTo($to);
            // Pawn moved 2 squares - set en passant target
            if (abs($rankDelta) === 2) {
                $targetRank = $piece->side === Side::WHITE ? $from->rank() + 1 : $from->rank() - 1;
                $this->enPassantTarget = new Position($from->file() . $targetRank);
            } else {
                // Any other pawn move clears the en passant target
                $this->enPassantTarget = null;
            }
        } else {
            // Non-pawn moves clear the en passant target
            $this->enPassantTarget = null;
        }
    }

    private function checkGameEndConditions(): void
    {
        $opponentSide = $this->activePlayer->side === Side::WHITE ? Side::BLACK : Side::WHITE;

        try {
            $opponentKingPosition = $this->board->getKingPosition($opponentSide);

            // Check if opponent is in check
            $isInCheck = $this->board->isSquareAttackedBy($opponentKingPosition, $this->activePlayer->side);

            if ($isInCheck) {
                // Check if opponent has any legal moves
                if (!$this->hasLegalMoves($opponentSide)) {
                    // Checkmate
                    $this->status = GameStatus::CHECKMATE;
                    $winnerSide = $this->activePlayer->side->value;
                    $loserSide = $opponentSide->value;
                    $this->recordThat(new Checkmate(
                        gameId: (string) $this->getGameId(),
                        winnerSide: $winnerSide,
                        loserSide: $loserSide
                    ));
                    $this->recordThat(new GameFinished(
                        gameId: (string) $this->getGameId(),
                        status: GameStatus::CHECKMATE->value,
                        winner: $winnerSide,
                        reason: 'checkmate'
                    ));
                    return;
                }
                // Just in check - emit CheckAnnounced
                $this->recordThat(new CheckAnnounced(gameId: (string) $this->getGameId()));
            } else {
                // Not in check - check for stalemate
                if (!$this->hasLegalMoves($opponentSide)) {
                    $this->status = GameStatus::STALEMATE;
                    $this->recordThat(new Stalemate(gameId: (string) $this->getGameId()));
                    $this->recordThat(new GameFinished(
                        gameId: (string) $this->getGameId(),
                        status: GameStatus::STALEMATE->value,
                        winner: null,
                        reason: 'stalemate'
                    ));
                    return;
                }
            }
        } catch (\RuntimeException $e) {
            // King not found - should not happen in a valid game
        }
    }

    private function hasLegalMoves(Side $side): bool
    {
        $moveValidator = new MoveValidator();

        // Create a temporary game with the correct active player
        $tempGame = new self();
        $tempGame->gameId = $this->gameId;
        $tempGame->playerOne = $this->playerOne;
        $tempGame->playerTwo = $this->playerTwo;
        $tempGame->board = $this->board->clone();
        $tempGame->activePlayer = $side === Side::WHITE ? $this->playerOne : $this->playerTwo;
        $tempGame->castlingRights = $this->castlingRights;
        $tempGame->enPassantTarget = $this->enPassantTarget;
        $tempGame->status = GameStatus::IN_PROGRESS;

        // Check all pieces of the given side for legal moves
        foreach ($this->board->getAllPositions() as $fromPosition) {
            if (!$tempGame->board->fieldHasPiece($fromPosition)) {
                continue;
            }

            $piece = $tempGame->board->getPiece($fromPosition);
            if ($piece->side !== $side) {
                continue;
            }

            // Check all possible destination squares
            for ($file = 'a'; $file <= 'h'; $file++) {
                for ($rank = 1; $rank <= 8; $rank++) {
                    $toPosition = new Position($file . $rank);

                    // Skip same position
                    if ($fromPosition->toString() === $toPosition->toString()) {
                        continue;
                    }

                    if ($moveValidator->isMoveLegal($tempGame, $fromPosition, $toPosition)) {
                        return true;
                    }
                }
            }
        }

        return false;
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

    // For testing purposes
    public function setEnPassantTarget(?Position $target): void
    {
        $this->enPassantTarget = $target;
    }

    // For testing purposes only
    public function setBoardForTesting(Board $board): void
    {
        $this->board = $board;
    }

    public function setActivePlayerForTesting(Side $side): void
    {
        $this->activePlayer = $side === Side::WHITE ? $this->playerOne : $this->playerTwo;
    }

    public function setStatusForTesting(GameStatus $status): void
    {
        $this->status = $status;
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
        // Always create a new board for proper event sourcing
        $this->board = new Board();
        $this->activePlayer = $this->playerOne->side === Side::WHITE ? $this->playerOne : $this->playerTwo;
        $this->castlingRights = CastlingRights::initial();
        $this->enPassantTarget = null;
        $this->status = GameStatus::IN_PROGRESS;
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

    protected function whenPiecePromoted(PiecePromoted $event): void
    {
        $position = Position::fromString($event->to);
        $piece = $this->board->getPiece($position);
        $piece->promote(PieceType::from($event->promotedTo));
        $this->endTurn();
    }

    protected function whenCheckmate(Checkmate $event): void
    {
        $this->status = GameStatus::CHECKMATE;
    }

    protected function whenStalemate(Stalemate $event): void
    {
        $this->status = GameStatus::STALEMATE;
    }

    protected function whenCastlingPerformed(CastlingPerformed $event): void
    {
        $kingFrom = Position::fromString($event->kingFrom);
        $kingTo = Position::fromString($event->kingTo);
        $rookFrom = Position::fromString($event->rookFrom);
        $rookTo = Position::fromString($event->rookTo);

        $king = $this->board->getPiece($kingFrom);
        $rook = $this->board->getPiece($rookFrom);

        $this->board->movePiece($king, $kingTo);
        $this->board->movePiece($rook, $rookTo);

        $this->castlingRights = $this->castlingRights->revokeForSide(Side::from($event->side), 'both');
        $this->endTurn();
    }

    protected function whenGameFinished(GameFinished $event): void
    {
        $this->status = GameStatus::from($event->status);
    }
}
