<?php

declare(strict_types=1);

namespace App\Tests\Domain\Chess;

use App\Domain\Chess\Board;
use App\Domain\Chess\Game;
use App\Domain\Chess\GameId;
use App\Domain\Chess\Player;
use App\Domain\Chess\Position;
use App\Domain\Chess\Service\MoveValidator;
use App\Domain\Chess\Side;
use PHPUnit\Framework\TestCase;

class MoveValidatorTest extends TestCase
{
    private MoveValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new MoveValidator();
    }

    public function testWhitePawnMovement(): void
    {
        $game = $this->createGame(Board::START_FEN);

        // White pawn can move forward one square
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e2'), new Position('e3')));
        // White pawn can move forward two squares from starting position
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e2'), new Position('e4')));
        // White pawn cannot move backwards
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('e2'), new Position('e1')));
        // White pawn cannot move sideways
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('e2'), new Position('d2')));
    }

    public function testBlackPawnMovement(): void
    {
        $game = $this->createGame(Board::START_FEN);

        // Filler move so it is black's turn
        $game->move(new Position('h2'), new Position('h3'));

        // Black pawn can move forward one square
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e7'), new Position('e6')));
        // Black pawn can move forward two squares from starting position
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e7'), new Position('e5')));
    }

    public function testPawnCapture(): void
    {
        $game = $this->createGame('4k3/8/8/3p1p2/4P3/8/8/4K3');

        // White pawn can capture diagonally
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e4'), new Position('d5')));
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e4'), new Position('f5')));
        // White pawn can move straight forward to empty square
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e4'), new Position('e5')));
    }

    public function testEnPassantCapture(): void
    {
        $game = $this->createGame('4k3/3p4/8/4P3/8/8/P7/4K3');

        // Filler move for white, then black plays d7-d5 setting the en passant target
        $game->move(new Position('a2'), new Position('a3'));
        $game->move(new Position('d7'), new Position('d5'));

        // White pawn can capture en passant
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e5'), new Position('d6')));
        // But not to a square that's not the en passant target
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('e5'), new Position('f6')));
    }

    public function testRookMovement(): void
    {
        $game = $this->createGame('4k3/8/8/8/8/8/4K3/R6R');

        // Rook can move horizontally
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('a1'), new Position('b1')));
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('a1'), new Position('g1')));

        // Rook cannot move diagonally
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('a1'), new Position('h8')));
    }

    public function testBishopMovement(): void
    {
        $game = $this->createGame('4k3/8/8/8/8/8/8/2B1K3');

        // Bishop can move diagonally
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('c1'), new Position('a3')));
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('c1'), new Position('h6')));

        // Bishop cannot move horizontally or vertically
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('c1'), new Position('c8')));
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('c1'), new Position('h1')));
    }

    public function testKnightMovement(): void
    {
        $game = $this->createGame('4k3/8/8/8/8/8/8/1N2K3');

        // Knight can move in L-shape
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('b1'), new Position('a3')));
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('b1'), new Position('c3')));

        // Knight cannot move to adjacent squares
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('b1'), new Position('b2')));
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('b1'), new Position('c2')));
    }

    public function testQueenMovement(): void
    {
        $game = $this->createGame('4k3/8/8/8/8/8/4K3/Q7');

        // Queen can move in any direction
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('a1'), new Position('a2'))); // vertical
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('a1'), new Position('b1'))); // horizontal
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('a1'), new Position('b2'))); // diagonal
    }

    public function testKingMovement(): void
    {
        $game = $this->createGame('4k3/8/8/8/8/8/8/4K3');

        // King can move one square in any direction
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e1'), new Position('e2')));
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e1'), new Position('f2')));
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e1'), new Position('f1')));

        // King cannot move two squares
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('e1'), new Position('e3')));
    }

    public function testCannotCaptureOwnPieces(): void
    {
        $game = $this->createGame('4k3/8/8/4P3/4P3/8/8/4K3');

        // White pawn cannot capture own piece
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('e4'), new Position('e5')));
    }

    public function testWrongPlayerTurn(): void
    {
        $game = $this->createGame('4k3/4p3/8/8/8/8/8/4K3');

        // It's white's turn, black cannot move
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('e7'), new Position('e6')));
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
}
