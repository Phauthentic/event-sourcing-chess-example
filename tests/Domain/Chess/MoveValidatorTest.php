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

    public function testPawnMovement(): void
    {
        // Create a minimal game with just the pieces we need
        $board = new Board();
        // Clear specific positions and add our test pieces
        $board->removePiece(new Position('e2'));
        $board->removePiece(new Position('e7'));

        $whitePawn = new Piece(Side::WHITE, PieceType::PAWN, new Position('e2'));
        $blackPawn = new Piece(Side::BLACK, PieceType::PAWN, new Position('e7'));

        $board->placePiece($whitePawn, new Position('e2'));
        $board->placePiece($blackPawn, new Position('e7'));

        $gameId = GameId::fromString('test-game');
        $player1 = new Player('White', Side::WHITE);
        $player2 = new Player('Black', Side::BLACK);

        $game = Game::create($gameId, $player1, $player2, $board, true); // Skip events for testing
        $game->setActivePlayerForTesting(Side::WHITE);

        // White pawn can move forward one square
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e2'), new Position('e3')));
        // White pawn can move forward two squares from starting position
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e2'), new Position('e4')));
        // White pawn cannot move backwards
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('e2'), new Position('e1')));
        // White pawn cannot move sideways
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('e2'), new Position('d2')));

        // Switch to black player turn
        $game->setActivePlayerForTesting(Side::BLACK);

        // Black pawn can move forward one square
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e7'), new Position('e6')));
        // Black pawn can move forward two squares from starting position
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e7'), new Position('e5')));
    }

    public function testPawnCapture(): void
    {
        $game = $this->createGameWithCustomBoard([
            'e4' => new Piece(Side::WHITE, PieceType::PAWN, new Position('e4')),
            'd5' => new Piece(Side::BLACK, PieceType::PAWN, new Position('d5')),
            'f5' => new Piece(Side::BLACK, PieceType::PAWN, new Position('f5')),
        ]);

        // White pawn can capture diagonally
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e4'), new Position('d5')));
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e4'), new Position('f5')));
        // White pawn can move straight forward to empty square
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e4'), new Position('e5')));
    }

    public function testEnPassantCapture(): void
    {
        // Set up en passant scenario: black pawn just moved d7-d5, white pawn on e5 can capture en passant to d6
        $game = $this->createGameWithCustomBoard([
            'e5' => new Piece(Side::WHITE, PieceType::PAWN, new Position('e5')),
            'd5' => new Piece(Side::BLACK, PieceType::PAWN, new Position('d5')),
        ]);

        // Set en passant target (d6)
        $game->setEnPassantTarget(new Position('d6'));

        // White pawn can capture en passant
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e5'), new Position('d6')));
        // But not to a square that's not the en passant target
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('e5'), new Position('f6')));
    }

    public function testRookMovement(): void
    {
        $game = $this->createGameWithCustomBoard([
            'a1' => new Piece(Side::WHITE, PieceType::ROOK, new Position('a1')),
            'h1' => new Piece(Side::WHITE, PieceType::ROOK, new Position('h1')),
        ], 'e2'); // Move white king to e2

        // Rook can move horizontally
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('a1'), new Position('b1')));
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('a1'), new Position('g1')));

        // Rook cannot move diagonally
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('a1'), new Position('h8')));
    }

    public function testBishopMovement(): void
    {
        $game = $this->createGameWithCustomBoard([
            'c1' => new Piece(Side::WHITE, PieceType::BISHOP, new Position('c1')),
        ]);

        // Bishop can move diagonally
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('c1'), new Position('a3')));
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('c1'), new Position('h6')));

        // Bishop cannot move horizontally or vertically
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('c1'), new Position('c8')));
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('c1'), new Position('h1')));
    }

    public function testKnightMovement(): void
    {
        $game = $this->createGameWithCustomBoard([
            'b1' => new Piece(Side::WHITE, PieceType::KNIGHT, new Position('b1')),
        ]);

        // Knight can move in L-shape
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('b1'), new Position('a3')));
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('b1'), new Position('c3')));

        // Knight cannot move to adjacent squares
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('b1'), new Position('b2')));
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('b1'), new Position('c2')));
    }

    public function testQueenMovement(): void
    {
        $game = $this->createGameWithCustomBoard([
            'a1' => new Piece(Side::WHITE, PieceType::QUEEN, new Position('a1')),
        ], 'e2'); // Make sure king is not blocking

        // Queen can move in any direction
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('a1'), new Position('a2'))); // vertical
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('a1'), new Position('b1'))); // horizontal
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('a1'), new Position('b2'))); // diagonal
    }

    public function testKingMovement(): void
    {
        $game = $this->createGameWithCustomBoard([
            'e1' => new Piece(Side::WHITE, PieceType::KING, new Position('e1')),
        ]);

        // King can move one square in any direction
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e1'), new Position('e2')));
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e1'), new Position('f2')));
        $this->assertTrue($this->validator->isMoveLegal($game, new Position('e1'), new Position('f1')));

        // King cannot move two squares
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('e1'), new Position('e3')));
    }

    public function testCannotCaptureOwnPieces(): void
    {
        $game = $this->createGameWithCustomBoard([
            'e4' => new Piece(Side::WHITE, PieceType::PAWN, new Position('e4')),
            'e5' => new Piece(Side::WHITE, PieceType::PAWN, new Position('e5')),
        ]);

        // White pawn cannot capture own piece
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('e4'), new Position('e5')));
    }

    public function testWrongPlayerTurn(): void
    {
        $game = $this->createGameWithCustomBoard([
            'e7' => new Piece(Side::BLACK, PieceType::PAWN, new Position('e7')),
        ]);

        // It's white's turn, black cannot move
        $this->assertFalse($this->validator->isMoveLegal($game, new Position('e7'), new Position('e6')));
    }

    private function createGameWithCustomBoard(array $pieces, string $whiteKingPos = 'e1'): Game
    {
        $board = new Board();

        // Clear the board by removing all pieces
        for ($rank = 1; $rank <= 8; $rank++) {
            for ($file = 'a'; $file <= 'h'; $file++) {
                $pos = $file . $rank;
                if ($board->fieldHasPiece(new Position($pos))) {
                    $board->removePiece(new Position($pos));
                }
            }
        }

        // Add kings (required for move validation)
        $board->placePiece(new Piece(Side::WHITE, PieceType::KING, new Position($whiteKingPos)), new Position($whiteKingPos));
        $board->placePiece(new Piece(Side::BLACK, PieceType::KING, new Position('e8')), new Position('e8'));

        // Add custom pieces
        foreach ($pieces as $pos => $piece) {
            $position = new Position($pos);
            if ($board->fieldHasPiece($position)) {
                $board->removePiece($position);
            }
            $board->placePiece($piece, $position);
        }

        $gameId = GameId::fromString('test-game');
        $player1 = new Player('White', Side::WHITE);
        $player2 = new Player('Black', Side::BLACK);

        return Game::create($gameId, $player1, $player2, $board, true); // Skip events for testing
    }
}