<?php

declare(strict_types=1);

namespace App\Tests\Domain\Chess;

use App\Domain\Chess\Board;
use App\Domain\Chess\Game;
use App\Domain\Chess\GameId;
use App\Domain\Chess\Piece;
use App\Domain\Chess\PieceType;
use App\Domain\Chess\Player;
use App\Domain\Chess\Position;
use App\Domain\Chess\Side;
use PHPUnit\Framework\TestCase;

class EnPassantTest extends TestCase
{
    public function testEnPassantCapture(): void
    {
        $gameId = GameId::fromString('en-passant-test');
        $player1 = new Player('White', Side::WHITE);
        $player2 = new Player('Black', Side::BLACK);

        $game = Game::create($gameId, $player1, $player2, new Board());

        // Set up custom board for testing with pawns in en passant position
        $board = new Board();

        // Clear all pieces
        for ($rank = 1; $rank <= 8; $rank++) {
            for ($file = 'a'; $file <= 'h'; $file++) {
                $pos = $file . $rank;
                if ($board->fieldHasPiece(new Position($pos))) {
                    $board->removePiece(new Position($pos));
                }
            }
        }

        // Place kings (required for move validation)
        $board->placePiece(new Piece(Side::WHITE, PieceType::KING, new Position('e1')), new Position('e1'));
        $board->placePiece(new Piece(Side::BLACK, PieceType::KING, new Position('e8')), new Position('e8'));

        // Place white pawn on e5
        $whitePawn = new Piece(Side::WHITE, PieceType::PAWN, new Position('e5'));
        $board->placePiece($whitePawn, new Position('e5'));

        // Place black pawn on d5
        $blackPawn = new Piece(Side::BLACK, PieceType::PAWN, new Position('d5'));
        $board->placePiece($blackPawn, new Position('d5'));

        $game->setBoardForTesting($board);
        $game->setEnPassantTarget(new Position('d6')); // Set by black's d7-d5 move

        // Now white can capture en passant: e5 pawn moves to d6
        $game->move(new Position('e5'), new Position('d6'));

        // Check that white pawn is now on d6
        $this->assertTrue($game->getBoard()->fieldHasPiece(new Position('d6')));
        $this->assertEquals(Side::WHITE, $game->getBoard()->getPiece(new Position('d6'))->side);
        $this->assertEquals(PieceType::PAWN, $game->getBoard()->getPiece(new Position('d6'))->type);

        // Check that black pawn on d5 has been captured and removed
        $this->assertFalse($game->getBoard()->fieldHasPiece(new Position('d5')));

        // Check that white pawn is no longer on e5
        $this->assertFalse($game->getBoard()->fieldHasPiece(new Position('e5')));
    }

    public function testEnPassantTargetSetAfterDoublePawnMove(): void
    {
        $board = new Board();
        $gameId = GameId::fromString('pawn-double-move-test');
        $player1 = new Player('White', Side::WHITE);
        $player2 = new Player('Black', Side::BLACK);

        $game = Game::create($gameId, $player1, $player2, $board);

        // Check that pawn is on e2
        $this->assertTrue($game->getBoard()->fieldHasPiece(new Position('e2')));

        // White moves e2 pawn two squares to e4
        $game->move(new Position('e2'), new Position('e4'));

        // En passant target should be set to e3
        $this->assertEquals('e3', $game->getEnPassantTarget()?->toString());
    }

    public function testEnPassantTargetClearedAfterNextMove(): void
    {
        $board = new Board();
        $gameId = GameId::fromString('en-passant-clear-test');
        $player1 = new Player('White', Side::WHITE);
        $player2 = new Player('Black', Side::BLACK);

        $game = Game::create($gameId, $player1, $player2, $board);

        // White moves e2 pawn two squares to e4
        $game->move(new Position('e2'), new Position('e4'));

        // En passant target should be set
        $this->assertNotNull($game->getEnPassantTarget());

        // Black makes a move (not en passant)
        $game->move(new Position('e7'), new Position('e6'));

        // En passant target should be cleared
        $this->assertNull($game->getEnPassantTarget());
    }
}