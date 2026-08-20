<?php

declare(strict_types=1);

namespace App\Tests\Domain\Chess;

use App\Domain\Chess\Board;
use App\Domain\Chess\Game;
use App\Domain\Chess\GameId;
use App\Domain\Chess\PieceType;
use App\Domain\Chess\Player;
use App\Domain\Chess\Position;
use App\Domain\Chess\Side;
use PHPUnit\Framework\TestCase;

class EnPassantTest extends TestCase
{
    public function testEnPassantCapture(): void
    {
        $game = Game::create(
            GameId::fromString('en-passant-test'),
            new Player('White', Side::WHITE),
            new Player('Black', Side::BLACK),
            Board::fromFen('4k3/3p4/8/4P3/8/8/P7/4K3')
        );

        // White makes a filler move so black can set up the en passant scenario
        $game->move(new Position('a2'), new Position('a3'));

        // Black plays the double pawn move d7-d5, allowing en passant on d6
        $game->move(new Position('d7'), new Position('d5'));

        // White captures en passant: e5 pawn takes on d6
        $game->move(new Position('e5'), new Position('d6'));

        $board = $game->board();
        $this->assertNotNull($board->pieceAt(new Position('d6')));
        $this->assertEquals(Side::WHITE, $board->pieceAt(new Position('d6'))->side);
        $this->assertEquals(PieceType::PAWN, $board->pieceAt(new Position('d6'))->type);

        // The captured black pawn is removed from d5 and the white pawn left e5
        $this->assertNull($board->pieceAt(new Position('d5')));
        $this->assertNull($board->pieceAt(new Position('e5')));
    }

    public function testEnPassantTargetSetAfterDoublePawnMove(): void
    {
        $game = $this->createGameWithDefaultBoard();

        $this->assertNotNull($game->board()->pieceAt(new Position('e2')));

        $game->move(new Position('e2'), new Position('e4'));

        $this->assertEquals('e3', $game->enPassantTarget()?->position);
    }

    public function testEnPassantTargetClearedAfterNextMove(): void
    {
        $game = $this->createGameWithDefaultBoard();

        $game->move(new Position('e2'), new Position('e4'));

        $this->assertNotNull($game->enPassantTarget());

        // Black makes a move (not en passant)
        $game->move(new Position('e7'), new Position('e6'));

        $this->assertNull($game->enPassantTarget());
    }

    private function createGameWithDefaultBoard(): Game
    {
        return Game::create(
            GameId::fromString('en-passant-test'),
            new Player('White', Side::WHITE),
            new Player('Black', Side::BLACK),
            new Board()
        );
    }
}
