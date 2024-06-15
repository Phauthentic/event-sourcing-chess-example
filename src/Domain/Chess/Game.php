<?php

declare(strict_types=1);

namespace App\Domain\Chess;

use App\Domain\Chess\Event\GameCreated;
use Phauthentic\EventSourcing\Aggregate\AbstractEventSourcedAggregate;
use Phauthentic\EventSourcing\Aggregate\Attribute\AggregateIdentifier;
use Phauthentic\EventSourcing\Aggregate\Attribute\AggregateVersion;
use Phauthentic\EventSourcing\Aggregate\Attribute\DomainEvents;

/**
 * @link https://en.wikipedia.org/wiki/Algebraic_notation_(chess)
 */
final class Game extends AbstractEventSourcedAggregate
{
    #[AggregateIdentifier]
    private GameId $gameId;

    #[DomainEvents]
    private array $domainEvents = [];

    #[AggregateVersion]
    protected int $aggregateVersion = 0;

    private array $fields = [];

    private array $pieces = [];

    private Player $activePlayer;

    public function __construct(
        GameId         $gameId,
        private ?Player $playerOne,
        private ?Player $playerTwo
    ) {
        $this->gameId = $gameId;
        $this->assertPlayersDonNotHaveTheSameSide($playerOne, $playerTwo);

        $this->populateBoard();

        $this->recordThat(GameCreated::create(
            $gameId->id,
            $playerOne->name,
            $playerTwo->name
        ));
    }

    public static function create(
        GameId $gameId,
        Player $playerOne,
        Player $playerTwo
    ): self {
        return new self(
            $gameId,
            $playerOne,
            $playerTwo
        );
    }

    /**
     * Sets the pieces on the board to their initial position for a new game.
     */
    private function populateBoard(): void
    {
        $this->setBlackPieces();
        $this->setWhitePieces();
    }

    /**
     * Generates the pawns for both players
     */
    private function setPawns(Side $side): void
    {
        switch ($side) {
            case Side::WHITE:
                $number = 2;
                break;
            case Side::BLACK:
                $number = 7;
                break;
        }

        $charCode = 96;
        for ($i = 0; $i < 8; $i++) {
            $charCode++;
            $this->fields[chr($charCode) . $number] = new Piece($side, PieceType::PAWN, new Position(chr($charCode) . $number));
        }
    }

    private function setBlackPieces(): void
    {
        $this->setPawns(Side::BLACK);

        $this->fields['a8'] = new Piece(Side::BLACK, PieceType::ROOK, new Position('a8'));
        $this->fields['h8'] = new Piece(Side::BLACK, PieceType::ROOK, new Position('a8'));
        $this->fields['b8'] = new Piece(Side::BLACK, PieceType::BISHOP, new Position('b8'));
        $this->fields['g8'] = new Piece(Side::BLACK, PieceType::BISHOP, new Position('g8'));
        $this->fields['c8'] = new Piece(Side::BLACK, PieceType::KNIGHT, new Position('c8'));
        $this->fields['f8'] = new Piece(Side::BLACK, PieceType::KNIGHT, new Position('f8'));
        $this->fields['d8'] = new Piece(Side::BLACK, PieceType::QUEEN, new Position('d8'));
        $this->fields['e8'] = new Piece(Side::BLACK, PieceType::KING, new Position('e8'));
    }

    private function setWhitePieces(): void
    {
        $this->setPawns(Side::WHITE);

        $this->fields['a1'] = new Piece(Side::WHITE, PieceType::ROOK, new Position('a8'));
        $this->fields['h1'] = new Piece(Side::WHITE, PieceType::ROOK, new Position('a8'));
        $this->fields['b1'] = new Piece(Side::WHITE, PieceType::BISHOP, new Position('b1'));
        $this->fields['g1'] = new Piece(Side::WHITE, PieceType::BISHOP, new Position('g1'));
        $this->fields['c1'] = new Piece(Side::WHITE, PieceType::KNIGHT, new Position('c1'));
        $this->fields['f1'] = new Piece(Side::WHITE, PieceType::KNIGHT, new Position('f1'));
        $this->fields['e1'] = new Piece(Side::WHITE, PieceType::QUEEN, new Position('e1'));
        $this->fields['d1'] = new Piece(Side::WHITE, PieceType::KING, new Position('d1'));
    }

    private function assertPlayersDonNotHaveTheSameSide(Player $playerWhite, Player $playerBlack): void
    {
        assert($playerWhite->side === $playerBlack->side, 'Players must not have the same side!');
    }

    public function move(Position $from, Position $to): void
    {
        foreach ($this->pieces as $piece) {
            if ($piece->position->equals($from)) {
                $this->assertActivePlayerHasThePiece($piece);

                $piece->move($to);
                $this->endTurn();

                return;
            }
        }
    }

    private function fieldHasPawn(Position $position): ?Piece
    {
        if (isset($this->fields[$position->toString()])) {
            return $this->fields[$position->toString()];
        }

        return null;
    }

    private function endTurn()
    {
        $this->activePlayer = $this->activePlayer === $this->playerOne ? $this->playerTwo : $this->playerOne;
    }

    protected function whenGameCreated(GameCreated $event): void
    {
        $this->gameId = GameId::fromString($event->boardId);
        //$this->playerOne = new Player($event->playerOne, Side::WHITE);
        //$this->playerTwo = new Player($event->playerTwo, Side::BLACK);
        //$this->activePlayer = $this->playerOne;
    }

    /**
     * Business rule assertion
     */
    private function assertActivePlayerHasThePiece(Piece $piece): void
    {
        if ($this->activePlayer->side !== $piece->side) {
            throw new ChessDomainException('It is not your turn!');
        }
    }

    public function toArray(): array
    {
        return [
            'boardId' => $this->gameId->id,
            //'playerOne' => $this->playerOne?->name,
            //'playerTwo' => $this->playerTwo?->name,
            'pieces' => $this->pieces,
            ///'activePlayer' => $this->activePlayer->name,
        ];
    }
}
