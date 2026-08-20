<?php

declare(strict_types=1);

namespace App\Tests\Domain\Chess;

use App\Domain\Chess\Board;
use App\Domain\Chess\CastlingRights;
use App\Domain\Chess\Event\CheckAnnounced;
use App\Domain\Chess\Event\GameStarted;
use App\Domain\Chess\Event\PieceCaptured;
use App\Domain\Chess\Event\PiecePromoted;
use App\Domain\Chess\Game;
use App\Domain\Chess\GameId;
use App\Domain\Chess\GameStatus;
use App\Domain\Chess\PieceType;
use App\Domain\Chess\Player;
use App\Domain\Chess\Position;
use App\Domain\Chess\Side;
use Phauthentic\EventStore\Event;
use PHPUnit\Framework\TestCase;

class GameTest extends TestCase
{
    public function testGameCreation(): void
    {
        $gameId = GameId::fromString('test-game');
        $player1 = new Player('Alice', Side::WHITE);
        $player2 = new Player('Bob', Side::BLACK);

        $game = Game::create($gameId, $player1, $player2, new Board());

        $this->assertEquals($gameId, $game->getGameId());
        $this->assertEquals($player1, $game->getActivePlayer());
        $this->assertEquals(GameStatus::IN_PROGRESS, $game->getStatus());
        $this->assertEquals(CastlingRights::initial(), $game->getCastlingRights());
        $this->assertNull($game->getEnPassantTarget());

        $events = $game->consumeAggregateEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(GameStarted::class, $events[0]);
        $this->assertEquals(Board::START_FEN, $events[0]->fen);
    }

    public function testPawnDoubleMoveSetsEnPassantTarget(): void
    {
        $game = $this->createGame('4k3/8/8/8/8/8/4P3/4K3');

        $game->move(new Position('e2'), new Position('e4'));

        // En passant target should be set to e3 (the square the pawn passed over)
        $this->assertEquals('e3', $game->getEnPassantTarget()?->position);
    }

    public function testEnPassantCapture(): void
    {
        $game = $this->createGame('4k3/3p4/8/4P3/8/8/P7/4K3');

        // White moves first (dummy move to make it black's turn)
        $game->move(new Position('a2'), new Position('a3'));

        // Black moves pawn two squares from d7 to d5
        $game->move(new Position('d7'), new Position('d5'));

        // Now it's white's turn and en passant target should be set to d6
        $this->assertEquals('d6', $game->getEnPassantTarget()?->position);

        // White captures en passant
        $game->move(new Position('e5'), new Position('d6'));

        $this->assertTrue($game->getBoard()->fieldHasPiece(new Position('d6')));
        $this->assertFalse($game->getBoard()->fieldHasPiece(new Position('d5')));
        $this->assertFalse($game->getBoard()->fieldHasPiece(new Position('e5')));
    }

    public function testCastlingKingside(): void
    {
        $game = $this->createGame('4k3/8/8/8/8/8/8/4K2R');

        // Kingside castling: king from e1 to g1, rook from h1 to f1
        $game->move(new Position('e1'), new Position('g1'));

        $this->assertTrue($game->getBoard()->fieldHasPiece(new Position('g1')));
        $this->assertTrue($game->getBoard()->fieldHasPiece(new Position('f1')));
        $this->assertFalse($game->getBoard()->fieldHasPiece(new Position('e1')));
        $this->assertFalse($game->getBoard()->fieldHasPiece(new Position('h1')));
    }

    public function testCastlingQueenside(): void
    {
        $game = $this->createGame('4k3/8/8/8/8/8/8/R3K3');

        // Queenside castling: king from e1 to c1, rook from a1 to d1
        $game->move(new Position('e1'), new Position('c1'));

        $this->assertTrue($game->getBoard()->fieldHasPiece(new Position('c1')));
        $this->assertTrue($game->getBoard()->fieldHasPiece(new Position('d1')));
    }

    public function testCastlingRightsRevokedAfterKingMove(): void
    {
        $game = $this->createGame('4k3/8/8/8/8/8/8/4K3');

        $this->assertTrue($game->getCastlingRights()->hasRights(Side::WHITE, 'kingside'));
        $this->assertTrue($game->getCastlingRights()->hasRights(Side::WHITE, 'queenside'));

        $game->move(new Position('e1'), new Position('e2'));

        $this->assertFalse($game->getCastlingRights()->hasRights(Side::WHITE, 'kingside'));
        $this->assertFalse($game->getCastlingRights()->hasRights(Side::WHITE, 'queenside'));
    }

    public function testCastlingRightsRevokedAfterRookMove(): void
    {
        $game = $this->createGame('4k3/8/8/8/8/8/8/4K2R');

        $this->assertTrue($game->getCastlingRights()->hasRights(Side::WHITE, 'kingside'));

        $game->move(new Position('h1'), new Position('h2'));

        $this->assertFalse($game->getCastlingRights()->hasRights(Side::WHITE, 'kingside'));
        $this->assertTrue($game->getCastlingRights()->hasRights(Side::WHITE, 'queenside'));
    }

    public function testCannotMoveWhenGameIsFinished(): void
    {
        $game = $this->createGame('4k3/8/8/8/8/8/8/4K3');

        $game->acceptDraw();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Game is not in progress');

        $game->move(new Position('e1'), new Position('e2'));
    }

    public function testAcceptedDrawFinishesTheGame(): void
    {
        $game = $this->createGame('4k3/8/8/8/8/8/8/4K3');

        $game->offerDraw();
        $game->acceptDraw();

        $this->assertEquals(GameStatus::DRAW_AGREED, $game->getStatus());
    }

    public function testPawnMove(): void
    {
        $game = $this->createGame('4k3/8/8/8/4P3/8/8/4K3');

        $game->move(new Position('e4'), new Position('e5'));

        $this->assertTrue($game->getBoard()->fieldHasPiece(new Position('e5')));
        $this->assertFalse($game->getBoard()->fieldHasPiece(new Position('e4')));
    }

    public function testCannotCaptureOwnPiece(): void
    {
        $game = $this->createGame('4k3/8/8/4P3/4P3/8/8/4K3');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid move');

        $game->move(new Position('e4'), new Position('e5'));
    }

    public function testPawnPromotionMovesAndPromotesThePawn(): void
    {
        $game = $this->createGame('4k3/6P1/8/8/8/8/8/4K3');

        $game->move(new Position('g7'), new Position('g8'), PieceType::QUEEN);

        $board = $game->getBoard();
        $this->assertFalse($board->fieldHasPiece(new Position('g7')));
        $this->assertEquals(PieceType::QUEEN, $board->getPiece(new Position('g8'))->type);

        $eventClasses = array_map(get_class(...), $game->consumeAggregateEvents());
        $this->assertContains(PiecePromoted::class, $eventClasses);
        // The promoted queen checks the black king along the back rank
        $this->assertContains(CheckAnnounced::class, $eventClasses);
    }

    /**
     * Replaying the recorded events into a fresh aggregate must reproduce
     * the exact same state as the aggregate that recorded them.
     */
    public function testReplayFromHistoryReproducesState(): void
    {
        $game = $this->createGame('r3k3/3p2P1/8/4P3/8/8/P7/4K2R');

        $game->move(new Position('e1'), new Position('g1'));  // white castles kingside
        $game->move(new Position('d7'), new Position('d5'));  // black double pawn move
        $game->move(new Position('e5'), new Position('d6'));  // white captures en passant
        $game->move(new Position('a8'), new Position('a2'));  // black rook captures a2 pawn
        $game->move(new Position('g7'), new Position('g8'), PieceType::QUEEN); // promotion with check

        $events = $game->consumeAggregateEvents();
        $eventClasses = array_map(get_class(...), $events);
        $this->assertContains(PieceCaptured::class, $eventClasses);
        $this->assertContains(PiecePromoted::class, $eventClasses);

        $replayed = $this->replay($events);

        $this->assertEquals($game->getBoard()->toFen(), $replayed->getBoard()->toFen());
        $this->assertEquals($game->getActivePlayer(), $replayed->getActivePlayer());
        $this->assertEquals($game->getStatus(), $replayed->getStatus());
        $this->assertEquals($game->getCastlingRights(), $replayed->getCastlingRights());
        $this->assertEquals($game->getEnPassantTarget(), $replayed->getEnPassantTarget());
        $this->assertEquals($game->getAggregateVersion(), $replayed->getAggregateVersion());
    }

    public function testReplayOfCustomStartingPositionReproducesBoard(): void
    {
        $fen = '4k3/3p4/8/4P3/8/8/P7/4K3';
        $game = $this->createGame($fen);

        $replayed = $this->replay($game->consumeAggregateEvents());

        $this->assertEquals($fen, $replayed->getBoard()->toFen());
    }

    private function createGame(string $fen): Game
    {
        return Game::create(
            GameId::fromString('test-game'),
            new Player('White', Side::WHITE),
            new Player('Black', Side::BLACK),
            Board::fromFen($fen)
        );
    }

    /**
     * @param array<int, object> $events
     */
    private function replay(array $events): Game
    {
        $storedEvents = [];
        foreach ($events as $index => $payload) {
            $storedEvents[] = new Event(
                aggregateId: 'test-game',
                aggregateVersion: $index + 1,
                event: get_class($payload),
                payload: $payload,
                createdAt: new \DateTimeImmutable()
            );
        }

        /** @var Game $replayed */
        $replayed = new \ReflectionClass(Game::class)->newInstanceWithoutConstructor();
        $replayed->applyEventsFromHistory($storedEvents);

        return $replayed;
    }
}
