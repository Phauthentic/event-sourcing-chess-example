<?php

declare(strict_types=1);

namespace App\Domain\Chess;

use App\Domain\Chess\Event\GameCreated;
use JsonSerializable;
use Phauthentic\EventSourcing\Aggregate\AbstractEventSourcedAggregate;
use Phauthentic\EventSourcing\Aggregate\Attribute\AggregateIdentifier;
use Phauthentic\EventSourcing\Aggregate\Attribute\AggregateVersion;
use Phauthentic\EventSourcing\Aggregate\Attribute\DomainEvents;

/**
 * @link https://en.wikipedia.org/wiki/Algebraic_notation_(chess)
 */
final class Game extends AbstractEventSourcedAggregate implements JsonSerializable
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
        private Player $white,
        private Player $black
    ) {
        $this->gameId = $gameId;
        $this->assertPlayersDonNotHaveTheSameSide($white, $black);

        $this->populateBoard();

        $this->recordThat(GameCreated::create(
            $gameId->id,
            $white->toArray(),
            $black->toArray()
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
     *
     * @return void
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
        $side === Side::WHITE ? $number = 2 : $number = 7;

        $charCode = 96;
        for ($i = 0; $i < 8; $i++) {
            $charCode++;
            $position = chr($charCode) . $number;
            $this->fields[$position] = new Piece($side, PieceType::PAWN, new Position($position));
        }
    }

    private function setBlackPieces(): void
        {
            $this->setPawns(Side::BLACK);

            $this->fields[Field::a8->value] = new Piece(Side::BLACK, PieceType::ROOK, new Position(Field::a8->value));
            $this->fields[Field::h8->value] = new Piece(Side::BLACK, PieceType::ROOK, new Position(Field::h8->value));
            $this->fields[Field::b8->value] = new Piece(Side::BLACK, PieceType::BISHOP, new Position(Field::b8->value));
            $this->fields[Field::g8->value] = new Piece(Side::BLACK, PieceType::BISHOP, new Position(Field::g8->value));
            $this->fields[Field::c8->value] = new Piece(Side::BLACK, PieceType::KNIGHT, new Position(Field::c8->value));
            $this->fields[Field::f8->value] = new Piece(Side::BLACK, PieceType::KNIGHT, new Position(Field::f8->value));
            $this->fields[Field::d8->value] = new Piece(Side::BLACK, PieceType::QUEEN, new Position(Field::d8->value));
            $this->fields[Field::e8->value] = new Piece(Side::BLACK, PieceType::KING, new Position(Field::e8->value));
        }

        private function setWhitePieces(): void
        {
            $this->setPawns(Side::WHITE);

            $this->fields[Field::a1->value] = new Piece(Side::WHITE, PieceType::ROOK, new Position(Field::a1->value));
            $this->fields[Field::h1->value] = new Piece(Side::WHITE, PieceType::ROOK, new Position(Field::h1->value));
            $this->fields[Field::b1->value] = new Piece(Side::WHITE, PieceType::BISHOP, new Position(Field::b1->value));
            $this->fields[Field::g1->value] = new Piece(Side::WHITE, PieceType::BISHOP, new Position(Field::g1->value));
            $this->fields[Field::c1->value] = new Piece(Side::WHITE, PieceType::KNIGHT, new Position(Field::c1->value));
            $this->fields[Field::f1->value] = new Piece(Side::WHITE, PieceType::KNIGHT, new Position(Field::f1->value));
            $this->fields[Field::d1->value] = new Piece(Side::WHITE, PieceType::QUEEN, new Position(Field::d1->value));
            $this->fields[Field::e1->value] = new Piece(Side::WHITE, PieceType::KING, new Position(Field::e1->value));
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

    private function endTurn(): void
    {
        $this->activePlayer = $this->activePlayer === $this->white ? $this->black : $this->white;
    }

    protected function whenGameCreated(GameCreated $event): void
    {
        $this->gameId = GameId::fromString($event->gameId);

        $this->white = new Player(
            $event->white['name'],
            Side::WHITE
        );

        $this->black = new Player(
            $event->black['name'],
            Side::BLACK
        );

        $this->populateBoard();
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
        $fields = [];
        foreach ($this->fields as $field) {
            $fields[$field->position->toString()] = $field->toArray();
        }

        return [
            'gameId' => (string)$this->gameId,
            'white' => $this->white->toArray(),
            'black' => $this->black->toArray(),
            'fields' => $fields
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
